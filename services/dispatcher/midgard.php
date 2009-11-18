<?php
/**
 * @package midcom_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Midgard dispatcher for MidCOM 3
 *
 * Dispatches Midgard HTTP requests to components.
 *
 * @package midcom_core
 */
class midcom_core_services_dispatcher_midgard implements midcom_core_services_dispatcher
{
    public $argv = array();
    public $get = array();
    public $component_name = '';
    public $request_method = 'GET';
    protected $route_array = array();
    protected $route_id = false;
    protected $action_arguments = array();
    protected $route_arguments = array();
    protected $core_routes = array();
    protected $component_routes = array();
    protected $route_definitions = null;
    protected $exceptions_stack = array();

    /**
     * Constructor will read arguments and GET parameters from the request URL and store
     * them to the context.
     */
    public function __construct()
    {
        if (isset($_GET))
        {
            $this->get = $_GET;
        }
        
        $this->request_method = $_SERVER['REQUEST_METHOD'];
        
        if (!extension_loaded('midgard'))
        {
            throw new Exception('Midgard 1.x is required for this MidCOM setup.');
        }

        $arg_string = substr($_MIDGARD['uri'], strlen($_MIDGARD['self']));
        if ($arg_string)
        {
            $argv = explode('/', $arg_string);
            foreach ($argv as $arg)
            {
                if (empty($arg))
                {
                    continue;
                }
                $this->argv[] = $arg;
            }
        }
    }

    /**
     * Pull data from currently loaded page into the context.
     */
    public function populate_environment_data()
    {
        $page = new midgard_page();
        $page->get_by_id($_MIDGARD['page']);
        
        // Style handling
        $style_id = $_MIDGARD['style'];
        if ($page->style)
        {
            $style_id = $page->style;
        }

        $_core = midcom_core_midcom::get_instance();

        $_core->context->page = $page;
        $_core->context->style_id = $style_id;
        $_core->context->prefix = $_MIDGARD['self'];
        $_core->context->uri = $_MIDGARD['uri'];
        $_core->context->component = $page->component;
        $_core->context->request_method = $this->request_method;
        
        $_core->context->webdav_request = false;
        if (   $_core->configuration->get('enable_webdav')
            && (   $this->request_method != 'GET'
                && $this->request_method != 'POST')
            )
        {
            // Serve this request with the full HTTP_WebDAV_Server
            $_core->context->webdav_request = true;
        }
        
        $host = new midgard_host();
        $host->get_by_id($_MIDGARD['host']);
        $_core->context->host = $host;   
        $_core->context->root = $host->root;
    }

    /**
     * Generate a valid cache identifier for a context of the current request
     */
    public function generate_request_identifier()
    {
        $_core = midcom_core_midcom::get_instance();

        if (isset($_core->context->cache_request_identifier))
        {
            // An injector has generated this already, let it be
            return;
        }

        $identifier_source  = "URI={$_core->context->uri}";
        $identifier_source .= ";COMP={$_core->context->component}";
        
        // TODO: Check language settings
        $identifier_source .= ';LANG=ALL';
        
        switch ($_core->context->cache_strategy)
        {
            case 'public':
                // Shared cache for everybody
                $identifier_source .= ';USER=EVERYONE';
                break;
            default:
                // Per-user cache
                if ($_core->authentication->is_user())
                {
                    $user = $_core->authentication->get_person();
                    $identifier_source .= ";USER={$user->username}";
                }
                else
                {
                    $identifier_source .= ';USER=ANONYMOUS';
                }
                break;
        }

        $_core->context->cache_request_identifier = md5($identifier_source);
    }

    public function initialize($component)
    {
        $_core = midcom_core_midcom::get_instance();

        // In main Midgard request we dispatch the component in connection to a page
        $this->component_name = $component;
        $_core->context->component = $component;
        $_core->context->component_instance = $_core->componentloader->load($this->component_name, $_core->context->page);
        if ($component == 'midcom_core')
        {
            // MidCOM core templates are already appended
            return;
        }
        $_core->templating->append_directory($_core->componentloader->component_to_filepath($_core->context->component) . '/templates');
    }
    
    /**
     * Get route definitions
     */
    public function get_routes()
    {
        $_core = midcom_core_midcom::get_instance();

        $_core->context->core_routes = $_core->configuration->normalize_routes($_core->configuration->get('routes'));
        $_core->context->component_routes = array();

        if (   !isset($_core->context->component_instance)
            || !$_core->context->component_instance)
        {
            return $_core->context->core_routes;
        }
        
        $_core->context->component_routes = $_core->configuration->normalize_routes($_core->context->component_instance->configuration->get('routes'));
        
        return array_merge($_core->context->component_routes, $_core->context->core_routes);
    }


    /**
     * Load a component and dispatch the request to it
     */
    public function dispatch()
    {
        $_core = midcom_core_midcom::get_instance();
        $this->route_definitions = $this->get_routes();

        $route_id_map = array();
        foreach ($this->route_definitions as $route_id => $route_configuration)
        {
            if (   isset($route_configuration['root_only'])
                && $route_configuration['root_only'])
            {
                // This route is to be run only with the root page
                if ($_core->context->page->id != $_core->context->root)
                {
                    // We're not in root page, skip
                    continue;
                }
            }
            $route_id_map[] = array
            (
                'route' => $route_configuration['route'],
                'route_id' => $route_id
            );
        }

        unset($route_configuration, $route_id);
        if (!$this->route_matches($route_id_map))
        {
            // TODO: Check message
            throw new midcom_exception_notfound('No route matches current URL');
        }
        unset($route_id_map);

        $success_flag = true; // Flag to tell if route ran successfully
        foreach ($this->route_array as $route)
        {
            try
            {   
                $success_flag = true; // before trying route it's marked success
                $this->dispatch_route($route);
            }
            catch (Exception $e)
            {
                if (get_class($e) == 'midcom_exception_unauthorized')
                {
                    // ACL exceptions override anything else
                    throw $e;
                }
                $this->exceptions_stack[] = $e; // Adding exception to exceptions stack
                $success_flag = false; // route failed
            }
            if ($success_flag) // Checking for success
            {
                break; // if we get here, controller run succesfully so bailing out from the loop
            }
        } // ending foreach

        if (!$success_flag) 
        {
            // if foreach is over and success flag is false throwing exeption
            $messages = '';
            foreach ($this->exceptions_stack as $exception)
            {
                switch (get_class($exception))
                {
                    case 'midcom_exception_unauthorized':
                        throw $exception;
                        // This will exit
                    case 'midcom_exception_httperror':
                        if (   $exception->getCode() != 405
                            || count($this->exceptions_stack) == 1)
                        {
                            // Throw the HTTP error as-is, except if it is a "Method not allowed" that isn't the only error
                            throw $exception;
                            // This will exit
                        }
                    default:
                        $messages .= $exception->getMessage() . "\n";
                        break;
                }
            }
            // 404 MultiFail
            throw new midcom_exception_notfound($messages);
        }
    }
    
    private function dispatch_route($route)
    {
        $_core = midcom_core_midcom::get_instance();

        $this->route_id = $route;
        $_core->context->route_id = $this->route_id;
        $selected_route_configuration = $this->route_definitions[$this->route_id];

        // Inform client of allowed HTTP methods
        header('Allow: ' . implode(', ', $selected_route_configuration['allowed_methods']));

        // Initialize controller
        $controller_class = $selected_route_configuration['controller'];
        $controller = new $controller_class($_core->context->component_instance);
        $controller->dispatcher = $this;
    
        // Define the action method for the route_id
        $action_method = strtolower($this->request_method) . "_{$selected_route_configuration['action']}";
        if ($this->request_method == 'HEAD')
        {
            // HEAD is like GET but returns no data
            $action_method = "get_{$selected_route_configuration['action']}";
        }

        // Run the route and set appropriate data
        $data = array();
        try
        {
            if (!method_exists($controller, $action_method))
            {
                if (   $this->request_method == 'GET'
                    || $this->request_method == 'POST'
                    || $this->request_method == 'HEAD')
                {
                    // Fallback for the legacy "action_XX" method names that had the action_x($route_id, &$data, $args) signature
                    // TODO: Remove when components are ready for it
                    $action_method = "action_{$selected_route_configuration['action']}";
                    if (!method_exists($controller, $action_method))
                    {
                        throw new midcom_exception_httperror("{$this->request_method} action {$selected_route_configuration['action']} not found", 405);
                    }
                    $controller->$action_method($this->route_id, $data, $this->action_arguments[$this->route_id]);
                }                    
                else
                {
                    throw new midcom_exception_httperror("{$this->request_method} not allowed", 405);
                }
            }
            else
            {
                $controller->data =& $data;
                $controller->$action_method($this->action_arguments[$this->route_id]);
            }
        }
        catch (Exception $e)
        {
            // Read controller's returned data to context before carrying on with exception handling
            $this->data_to_context($selected_route_configuration, $data);
            throw $e;
        }

        $this->data_to_context($selected_route_configuration, $data);
    }
    
    private function is_core_route($route_id)
    {
        $context = midcom_core_midcom::get_instance()->context;

        if (!isset($context->component_routes))
        {
            return false;
        }
        if (isset($context->component_routes[$route_id]))
        {
            return false;
        }
        
        return true;
    }

    private function data_to_context($route_configuration, $data)
    {
        $context = midcom_core_midcom::get_instance()->context;

        if ($this->is_core_route($this->route_id))
        {
            $component_name = 'midcom_core';
        }
        else
        {
            $component_name = $context->component;
        }
        
        $context->set_item($component_name, $data);
        
        // Set other context data from route
        if (isset($route_configuration['mimetype']))
        {
            $context->mimetype = $route_configuration['mimetype'];
        }
        if (isset($route_configuration['template_entry_point']))
        {
            $context->template_entry_point = $route_configuration['template_entry_point'];
        }
        if (isset($route_configuration['content_entry_point']))
        {
            $context->content_entry_point = $route_configuration['content_entry_point'];
        }
    }

    /**
     * Generates an URL for given route_id with given arguments
     *
     * @param string $route_id the id of the route to generate a link for
     * @param array $args associative arguments array
     * @return string url
     */
    public function generate_url($route_id, array $args, midgard_page $page = null, $component = null)
    {
        $_core = midcom_core_midcom::get_instance();

        if (   is_null($page)
            && !is_null($component))
        {
            // Find a page matching the requested component
            $qb = new midgard_query_builder('midgard_page');
            $qb->add_constraint('component', '=', $component);
            $qb->add_constraint('up', 'INTREE', $_core->context->root);
            $qb->set_limit(1);
            $pages = $qb->execute();
            if (empty($pages))
            {
                throw new OutOfBoundsException("No page matching component {$component} found");
            }
            $page = $pages[0];
        }

        if (!is_null($page))
        {
            $_core->context->create();
            $this->set_page($page);
            $this->initialize($_core->context->page->component);
        }

        $route_definitions = $this->get_routes();
        if (!isset($route_definitions[$route_id]))
        {
            throw new OutOfBoundsException("route_id '{$route_id}' not found in routes configuration");
        }
        $route = $route_definitions[$route_id]['route'];
        $link = $route;

        foreach ($args as $key => $value)
        {
            if (is_array($value))
            {
                $value_array = array();
                foreach ($value as $part)
                {
                    if (empty($part))
                    {
                        continue;
                    }
                    $value_array[] = $part;
                }
                
                $value = implode('.', $value_array);

                // This is a token replacement, add the type hint
                $key = "token:{$key}";
            }

            $link = str_replace("{\${$key}}", $value, $link);
        }

        if (preg_match_all('%\{$(.+?)\}%', $link, $link_matches))
        {
            throw new UnexpectedValueException("Missing arguments matching route '{$route_id}' of {$this->component_name}: " . implode(', ', $link_remaining_args));
        }
    
        if (!is_null($page))
        {
            $url = preg_replace('%/{2,}%', '/', $_core->context->prefix . $link);
            $_core->context->delete();
            return $url;
        }

        return preg_replace('%/{2,}%', '/', $_core->context->prefix . $link);
    }


    /**
     * Tries to match one route from an array of route definitions
     * associated with route_id route_ids
     *
     * The array should look something like this:
     * array
     * (
     *     '/view/{guid:article_id}/' => 'view',
     *     '/?articleid={int:article_id}' => 'view',
     *     '/foo/bar' => 'someroute_id',
     *     '/latest/{string:category}/{int:number}' => 'categorylatest',
     * )
     * The route parts are automatically normalized to end with trailing slash
     * if they don't contain GET arguments
     *
     * @param array $routes map of routes to route_ids
     * @return boolean indicating if a route could be matched or not
     */
    public function route_matches($routes)
    {
        // make a normalized string of $argv
        $argv_str = preg_replace('%/{2,}%', '/', '/' . implode('/', $this->argv) . '/');

        $this->action_arguments = array();
        
//        foreach ($routes as $route => $route_id)
        foreach ($routes as $r)
        {
            $route = $r['route'];
            $route_id = $r['route_id'];
            
            $this->action_arguments[$route_id] = array();
            
            // Reset variables
            list ($route_path, $route_get, $route_args) = midcom_core_midcom::get_instance()->configuration->split_route($route);
            
            if (!preg_match_all('%\{\$(.+?)\}%', $route_path, $route_path_matches))
            {
                // Simple route (only static arguments)
                if (   $route_path === $argv_str
                    && (   !$route_get
                        || $this->get_matches($route_get, $route))
                    )
                {
                    // echo "DEBUG: simple match route_id:{$route_id}\n";
                    $this->route_array[] = $route_id;
                }
                if ($route_args) // Route @ set
                {
                    $path = explode('@', $route_path);
                    if (preg_match('%' . str_replace('/', '\/', $path[0]) . '/(.*)\/%', $argv_str, $matches))
                    {
                        $this->route_array[] = $route_id;
                        $this->action_arguments[$route_id]['variable_arguments'] = explode('/', $matches[1]);
                    }
                }
                // Did not match, try next route
                continue;
            }
            // "complex" route (with variable arguments)
            if(preg_match('%@%', $route, $match))
            {   
                $route_path_regex = '%^' . str_replace('%', '\%', preg_replace('%\{(.+?)\}\@%', '([^/]+?)', $route_path)) . '(.*)%';
            }
            else 
            {
                $route_path_regex = '%^' . str_replace('%', '\%', preg_replace('%\{(.+?)\}%', '([^/]+?)', $route_path)) . '$%';
            }
//            echo "DEBUG: route_path_regex:{$route_path_regex} argv_str:{$argv_str}\n";
            if (!preg_match($route_path_regex, $argv_str, $route_path_regex_matches))
            {
                // Does not match, NEXT!
                continue;
            }
            if (   $route_get
                && !$this->get_matches($route_get, $route))
            {
                // We have GET part that could not be matched, NEXT!
                continue;
            }

            // We have a complete match, setup route_id arguments and return
            $this->route_array[] = $route_id;
            // Map variable arguments

            foreach ($route_path_matches[1] as $index => $varname)
            {
                $variable_parts = explode(':', $varname);
                if(count($variable_parts) == 1)
                {
                    $type_hint = '';
                }
                else
                {
                    $type_hint = $variable_parts[0];
                }
                                
                // Strip type hints from variable names
                $varname = preg_replace('/^.+:/', '', $varname);

                if ($type_hint == 'token')
                {
                    // Tokenize the argument to handle resource typing
                    $this->action_arguments[$route_id][$varname] = $this->tokenize_argument($route_path_regex_matches[$index + 1]);
                }
                else
                {
                    $this->action_arguments[$route_id][$varname] = $route_path_regex_matches[$index + 1];
                }
                
                if (preg_match('%@%', $route, $match)) // Route @ set
                {
                    $path = explode('@', $route_path);
                    if (preg_match('%' . str_replace('/', '\/', preg_replace('%\{(.+?)\}%', '([^/]+?)', $path[0])) . '/(.*)\/%', $argv_str, $matches))
                    {
                        $this->route_array[] = $route_id;
                        $this->action_arguments[$route_id] = explode('/', $matches[1]);
                    }
                }
                
            }
            //return true;
        }

        // No match
        if(count($this->route_array) == 0)
        {
            return false;
        }
        return true;
    }
    
    private function tokenize_argument($argument)
    {
        $tokens = array
        (
            'identifier' => '',
            'variant'    => '',
            'language'   => '',
            'type'       => 'html',
        );
        $argument_parts = explode('.', $argument);

        // First part is always identifier
        $tokens['identifier'] = $argument_parts[0];

        if (count($argument_parts) == 2)
        {
            // If there are two parts, the second is type
            $tokens['type'] = $argument_parts[1];
        }
        
        if (count($argument_parts) >= 3)
        {
            // If there are three parts, then second is variant and third is type
            $tokens['variant'] = $argument_parts[1];
            $tokens['type'] = $argument_parts[2];
        }

        if (count($argument_parts) >= 4)
        {
            // If there are four or more parts, then third is language and fourth is type
            $tokens['language'] = $argument_parts[2];
            $tokens['type'] = $argument_parts[3];
        }
        
        return $tokens;
    }

    /**
     * Checks GET part of a route definition and places arguments as needed
     *
     * @access private
     * @param string $route_get GET part of a route definition
     * @param string $route full route definition (used only for error reporting)
     * @return boolean indicating match/no match
     *
     * @fixme Move action arguments to subarray
     */
    private function get_matches(&$route_get, &$route)
    {
        /**
         * It's probably faster to check against $route_get before calling this method but
         * we want to be robust
         */
        if (empty($route_get))
        {
            return true;
        }

        if (!preg_match_all('%\&?(.+?)=\{(.+?)\}%', $route_get, $route_get_matches))
        {
            // Can't parse arguments from route_get
            throw new UnexpectedValueException("GET part of route '{$route}' ('{$route_get}') cannot be parsed");
        }

        /*
        echo "DEBUG: route_get_matches\n===\n";
        print_r($route_get_matches);
        echo "===\n";
        */

        foreach ($route_get_matches[1] as $index => $get_key)
        {
            //echo "this->get[{$get_key}]:{$this->get[$get_key]}\n";
            if (   !isset($this->get[$get_key])
                || empty($this->get[$get_key]))
            {
                // required GET parameter not present, return false;
                $this->action_arguments = array();
                return false;
            }
            
            preg_match('%/{\$([a-zA-Z]+):([a-zA-Z]+)}/%', $route_get_matches[2][$index], $matches);
            
            if(count($matches) == 0)
            {
                $type_hint = '';
            }
            else
            {
                $type_hint = $matches[1];
            }
                
            // Strip type hints from variable names
            $varname = preg_replace('/^.+:/', '', $route_get_matches[2][$index]);
                            
            if ($type_hint == 'token')
            {
                 // Tokenize the argument to handle resource typing
                $this->action_arguments[$varname] = $this->tokenize_argument($this->get[$get_key]);
            }
            else
            {
                $this->action_arguments[$varname] = $this->get[$get_key];
            }
        }

        // Unlike in route_matches falling through means match
        return true;
    }
    
    public function set_page(midgard_page $page)
    {
        $context = midcom_core_midcom::get_instance()->context;

        $context->page = $page;
        $context->prefix = $this->get_page_prefix();
    }
    
    private function get_page_prefix()
    {
        $_core = midcom_core_midcom::get_instance();
        $context = $_core->context;


        if (!$context->page)
        {
            throw new Exception("No page set for the manual dispatcher");
        }
        
        static $prefixes = array();
        if (isset($prefixes[$context->page->id]))
        {
            return $prefixes[$context->page->id];
        }
    
        $prefix = '/';
        $root_id = 0;
        if (isset($context->host))
        {
            $host_mc = midgard_host::new_collector('id', $context->host->id);
            $host_mc->set_key_property('root');
            $host_mc->add_value_property('prefix');
            $host_mc->execute();
            $roots = $host_mc->list_keys();
            if (!$roots)
            {
                throw new Exception("Failed to load root page data for host {$context->host->id}");
            }
            $root_id = null;
            foreach ($roots as $root => $array)
            {
                $root_id = $root;
                $prefix = $host_mc->get_subkey($root, 'prefix') . '/';
                break;
            }
        }
        if ($_core->configuration->get('root_page_id'))
        {
            // FIXME: Move to context
            $root_id = $_core->configuration->get('root_page_id');
        }
        
        if ($context->page->id == $root_id)
        {
            // We're requesting prefix for the root page
            $prefixes[$context->page->id] = $prefix;
            return $prefix;
        }
        
        $page_path = '';
        $page_id = $context->page->id;
        while (   $page_id
               && $page_id != $root_id)
        {
            $parent_mc = midgard_page::new_collector('id', $page_id);
            $parent_mc->set_key_property('up');
            $parent_mc->add_value_property('name');
            $parent_mc->execute();
            $parents = $parent_mc->list_keys();
            foreach ($parents as $parent => $array)
            {
                $page_id = $parent;
                $page_path = $parent_mc->get_subkey($parent, 'name') . "/{$page_path}";
            }
        }

        $prefixes[$context->page->id] = $prefix . $page_path;
        return $prefix . $page_path;
    }

    public function get_midgard_connection()
    {
        return $_MIDGARD_CONNECTION;
    }
    
    public function get_mgdschema_classes()
    {
        static $mgdschemas = array();
        if (empty($mgdschemas))
        {
            foreach ($_MIDGARD['schema']['types'] as $classname => $null)
            {
                $mgdschemas[] = $classname;
            }
        }
        return $mgdschemas;
    }
}
