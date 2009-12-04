<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Manual dispatcher for Midgard MVC
 *
 * Dispatches requested route and controller of components.
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_services_dispatcher_manual implements midgardmvc_core_services_dispatcher
{
    public $component_name = '';
    public $component_instance = false;
    private $page = null;
    protected $route_id = false;
    protected $action_arguments = array();
    public $request_method = 'GET';

    public function __construct()
    {
        $this->_core = midgardmvc_core::get_instance();

        if (isset($_GET))
        {
            $this->get = $_GET;
        }
        
        if (isset($_SERVER['REQUEST_METHOD']))
        {
            $this->request_method = $_SERVER['REQUEST_METHOD'];
        }
    }

    /**
     * Pull data from environment into the context.
     */
    public function populate_environment_data()
    {
        if (isset($_MIDGARD['host']))
        {
            $host = new midgard_host();
            $host->get_by_id($_MIDGARD['host']);
            $this->_core->context->host = $host;
            $this->_core->context->root = $host->root;
            $this->_core->context->style_id = $this->_core->context->host->style;
            $this->_core->context->prefix = $host->prefix;
        }
        
        if ($this->_core->context->get_current_context() != 0)
        {
            // This is a subrequest, copy some context data from context 0
            $this->_core->context->prefix = $this->_core->context->get_item('prefix', 0);
            $this->_core->context->root = $this->_core->context->get_item('root', 0);
            $this->_core->context->webdav_request = $this->_core->context->get_item('webdav_request', 0);
            $this->_core->context->style_id = $this->_core->context->get_item('style_id', 0);
        }

        $this->_core->context->cache_enabled = $this->_core->configuration->services_cache_configuration['enabled'];

        if (!$this->page)
        {
            $this->_core->context->component = $this->component_name;
            return;
        }
           
        $this->_core->context->self = $this->get_page_prefix();
        $this->_core->context->uri = $this->_core->context->self;
        foreach ($this->argv as $arg)
        {
            $this->_core->context->uri .= "{$arg}/";
        }
              
        $this->_core->context->component = $this->page->component;
        $this->_core->context->page = $this->page;
        
        if ($this->page->style)
        {
            $this->_core->context->style_id = $this->page->style;
        }
        
        $this->_core->templating->append_page($this->page->id);
    }

    public function generate_request_identifier()
    {
        if (isset($this->_core->context->cache_request_identifier))
        {
            // An injector has generated this already, let it be
            return;
        }

        $identifier_source  = "URI={$this->_core->context->uri}";
        $identifier_source .= ";COMP={$this->_core->context->component}";
        
        // TODO: Check language settings
        $identifier_source .= ';LANG=ALL';
        
        switch ($this->_core->context->cache_strategy)
        {
            case 'public':
                // Shared cache for everybody
                $identifier_source .= ';USER=EVERYONE';
                break;
            default:
                // Per-user cache
                if ($this->_core->authentication->is_user())
                {
                    $user = $this->_core->authentication->get_person();
                    $identifier_source .= ";USER={$user->username}";
                }
                else
                {
                    $identifier_source .= ';USER=ANONYMOUS';
                }
                break;
        }

        $this->_core->context->cache_request_identifier = md5($identifier_source);
    }
    
    public function initialize($component)
    {
        $this->component_name = $component;
        $this->_core->context->component = $component;
        
        if ($this->page)
        {
            $this->_core->context->component_instance = $this->_core->componentloader->load($this->component_name, $this->page);
        }
        else
        {
            $this->_core->context->component_instance = $this->_core->componentloader->load($this->component_name);
        }
        
        $this->_core->templating->append_directory($this->_core->componentloader->component_to_filepath($this->component_name) . '/templates');
    }
    
    public function get_routes()
    {
        $component_routes = $this->_core->context->component_instance->configuration->get('routes');
        
        return $component_routes;
    }
    
    public function set_page(midgard_page $page)
    {
        $this->page = $page;
    }

    public function resolve_page($path)
    {
        $req = new midgardmvc_core_helpers_request();
        $page = $req->resolve_page($path);
        $this->argv = $req->argv;
        return $page;
    }

    private function get_page_prefix()
    {
        if (!$this->page)
        {
            throw new Exception("No page set for the manual dispatcher");
        }

        $prefix = $this->_core->context->prefix;
        
        $root_id = $this->_core->context->root;
        if ($this->page->id == $root_id)
        {
            return $prefix;
        }
        
        $page_path = '';
        $page_id = $this->page->id;
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
        
        return $prefix . $page_path;
    }
    
    public function set_route($route_id, array $arguments)
    {
        $this->route_id = $route_id;
        $this->_core->context->route_id = $this->route_id;
        $this->action_arguments = $arguments;
    }

    /**
     * Load a component and dispatch the request to it
     */
    public function dispatch()
    {
        $route_definitions = $this->get_routes();

        $selected_route_configuration = $route_definitions[$this->route_id];

        $controller_class = $selected_route_configuration['controller'];
        $controller = new $controller_class($this->_core->context->component_instance);
        
        // Define the action method for the route_id
        $action_method = strtolower($this->request_method) . "_{$selected_route_configuration['action']}";

        $data = array();
        if (!method_exists($controller, $action_method))
        {
            if (   $this->request_method == 'GET'
                || $this->request_method == 'POST'
                || $this->request_method == 'HEAD')
            {
                // Sometimes GET-only routes are dynamic_loaded on pages where we do a POST, we need to support that
                $action_method = "get_{$selected_route_configuration['action']}";
                if (!method_exists($controller, $action_method))
                {
                    // Fallback for the legacy "action_XX" method names that had the action_x($route_id, &$data, $args) signature
                    // TODO: Remove when components are ready for it
                    $action_method = "action_{$selected_route_configuration['action']}";
                    if (!method_exists($controller, $action_method))
                    {
                        throw new midgardmvc_exception_notfound("Action {$selected_route_configuration['action']} not found");
                    }
                    $controller->$action_method($this->route_id, $data, $this->action_arguments);
                }
                else
                {
                    $controller->$action_method($this->action_arguments);
                }
            }
            else
            {
                throw new midgardmvc_exception_httperror("{$this->request_method} not allowed", 405);
            }
        }
        else
        {
            $controller->data =& $data;
            $controller->$action_method($this->action_arguments);
        }

        if ($this->is_core_route($this->route_id))
        {
            $component_name = 'midgardmvc_core';
        }
        else
        {
            $component_name = $this->component_name;
        }
        $this->_core->context->set_item($component_name, $data);
        
        // Set other context data from route
        if (isset($selected_route_configuration['mimetype']))
        {
            $this->_core->context->mimetype = $selected_route_configuration['mimetype'];
        }
        if (isset($selected_route_configuration['template_entry_point']))
        {
            $this->_core->context->template_entry_point = $selected_route_configuration['template_entry_point'];
        }
        if (isset($selected_route_configuration['content_entry_point']))
        {
            $this->_core->context->content_entry_point = $selected_route_configuration['content_entry_point'];
        }
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
            list ($route_path, $route_get, $route_args) = $this->_core->configuration->split_route($route);
            
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

    private function is_core_route($route_id)
    {
        if (!isset($this->_core->context->component_routes))
        {
            return false;
        }
        if (isset($this->_core->context->component_routes[$route_id]))
        {
            return false;
        }
        
        return true;
    }

    /**
     * Generates an URL for given route_id with given arguments
     *
     * @param string $route_id the id of the route to generate a link for
     * @param array $args associative arguments array
     * @return string url
     */
    public function generate_url($route_id, array $args)
    {
        return '';
    }

    public function get_midgard_connection()
    {
        if (method_exists('midgard_connection', 'get_instance'))
        {
            // Midgard 9.09 onwards
            return midgard_connection::get_instance();
        }
        if (!isset($_MIDGARD_CONNECTION))
        {
            return null;
        }
        // Midgard 8.09 or 9.03
        return $_MIDGARD_CONNECTION;
    }

    public function get_mgdschema_classes()
    {
        static $mgdschemas = array();
        if (empty($mgdschemas))
        {
            // Get the classes from PHP5 reflection
            $re = new ReflectionExtension('midgard2');
            $classes = $re->getClasses();
            foreach ($classes as $refclass)
            {
                $parent_class = $refclass->getParentClass();
                if (!$parent_class)
                {
                    continue;
                }
                if ($parent_class->getName() == 'midgard_object')
                {
                    $mgdschemas[] = $refclass->getName();
                }
            }
        }
        return $mgdschemas;
    }
}
?>
