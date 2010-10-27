<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Midgard dispatcher for Midgard MVC
 *
 * Dispatches Midgard HTTP requests to components.
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_services_dispatcher_midgard2 implements midgardmvc_core_services_dispatcher
{
    protected $exceptions_stack = array();

    protected $session_is_started = false;

    /**
     * Root node used for this Midgard MVC site, as provided by a hierarchy provider
     *
     * @var midgardmvc_core_providers_hierarchy_node
     */
    protected $_root_node = null;

    /**
     * Read the request configuration and parse the URL
     */
    public function __construct()
    {
        $this->midgardmvc = midgardmvc_core::get_instance();
    }

    /**
     * Parse request URL into components and return a corresponding MVC request object
     *
     * @return midgardmvc_core_request
     */
    public function get_request()
    {
        $request = new midgardmvc_core_request();
        if (function_exists('getallheaders'))
        {
            // TODO: Check for GData and CMIS compatible X-Method-Override
        }
        $request->set_method($_SERVER['REQUEST_METHOD']);
        
        // Parse URL into components
        $url_components = parse_url("http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}");

        // Handle GET parameters
        if (!empty($url_components['query']))
        {
            $get_parameters = array();
            parse_str($url_components['query'], $get_parameters);
            $request->set_query($get_parameters);
        }
        
        $request->resolve_node($url_components['path']);

        return $request;
    }

    public function header($string, $replace = true, $http_response_code = null)
    {
        if ($http_response_code === null)
        {
            header($string, $replace);
        }
        else
        {
            header($string, $replace, $http_response_code);
        }
    }

    public function headers_sent()
    {
        return headers_sent();
    }

    public function setcookie($name, $value = '', $expire = 0, $path = '/', $domain = null, $secure = false, $httponly = false)
    {
        return setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
    }

    public function session_start()
    {
        $res = session_start();

        if ($res)
        {
            $this->session_is_started = true;
        }

        return $res;
    }

    public function session_has_var($name)
    {
        return isset($_SESSION[$name]);
    }

    public function session_get_var($name)
    {
        return $_SESSION[$name];
    }

    public function session_set_var($name, $value)
    {
        $_SESSION[$name] = $value;
    }

    public function session_commit()
    {
        session_write_close();
        $this->session_is_started = false;
    }

    public function session_is_started()
    {
        return $this->session_is_started;
    }

    public function end_request()
    {
        exit();
    }

    /**
     * Load a component and dispatch the request to it
     */
    public function dispatch(midgardmvc_core_request $request)
    {
        $routes = $this->midgardmvc->component->get_routes($request);
        
        $matched_routes = array();
        // make a normalized string of $argv
        $argv_str = preg_replace('%/{2,}%', '/', '/' . implode('/', $request->get_arguments()) . '/');
        $query = $request->get_query();
        foreach ($routes as $route)
        {
            $matches = $route->check_match($argv_str, $query);
            if (!is_null($matches))
            {
                $matched_routes[$route->id] = $matches;
            }
        }

        if (!$matched_routes)
        {
            // TODO: Check message
            throw new midgardmvc_exception_notfound('No route matches current URL ' . $request->get_path());
        }

        $matched_routes = array_reverse($matched_routes);
        foreach ($matched_routes as $route_id => $arguments)
        {
            $request->set_route($routes[$route_id]);
            $this->dispatch_route($request, $arguments);
        }
    }
    
    private function dispatch_route(midgardmvc_core_request $request, array $arguments)
    {
        $route = $request->get_route();

        // Initialize controller and pass it the request object
        $controller_class = $route->controller;
        $controller = new $controller_class($request);
        
        // Define the action method for the route_id
        $request_method = $request->get_method();
        $action_method = "{$request_method}_{$route->action}";
        if ($request_method == 'head')
        {
            // HEAD is like GET but returns no data
            $action_method = "get_{$route->action}";
        }

        // Run the route and set appropriate data
        try
        {
            $controller->data = array();
            if (!method_exists($controller, $action_method))
            {
                throw new midgardmvc_exception_httperror("{$request_method} method not allowed", 405);
            }
            $controller->$action_method($arguments);
        }
        catch (Exception $e)
        {
            // Read controller's returned data to context before carrying on with exception handling
            $this->data_to_request($request, $controller->data);
            throw $e;
        }

        $this->data_to_request($request, $controller->data);
    }

    private function data_to_request(midgardmvc_core_request $request, array $data)
    {
        $components = $request->get_component_chain();
        foreach ($components as $component)
        {
            $request->set_data_item($component->name, $data);
        }
        $request->set_data_item('current_component', $data);
        
        // Set other request data from route
        $route = $request->get_route();
        $request->set_data_item('mimetype', $route->mimetype);
    }

    /**
     * Generates an URL for given route_id with given arguments
     *
     * @param string $route_id the id of the route to generate a link for
     * @param array $args associative arguments array
     * @param mixed $intent Component name, node object, node GUID or node path
     * @return string url
     */
    public function generate_url($route_id, array $args, $intent)
    {
        // Create a request from the intent and assign it to a context
        $request = midgardmvc_core_request::get_for_intent($intent);
        $this->midgardmvc->context->create($request);
        $routes = $this->midgardmvc->component->get_routes($request);
        if (!isset($routes[$route_id]))
        {
            if (empty($routes))
            {
                throw new OutOfBoundsException("Route ID '{$route_id}' not found in routes of request. Routes configuration is also empty " . $request->get_identifier());
            }
            throw new OutOfBoundsException("Route ID '{$route_id}' not found in routes of request " . $request->get_identifier());
        }
        $route = $routes[$route_id];
        $request->set_arguments($route->set_variables($args));

        $this->midgardmvc->context->delete();
        return $request->get_path();
    }

    public function get_midgard_connection()
    {
        return midgard_connection::get_instance();
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
