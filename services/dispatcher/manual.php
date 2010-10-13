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
    protected $action_arguments = array();
    public $get = array();
    private $request = null;

    public function __construct()
    {
        $this->midgardmvc = midgardmvc_core::get_instance();

        // Ensure we have a blank request object available for get_request
        $this->request = new midgardmvc_core_request();
    }

    /**
     * Override the request object returned by get_request. This can be used for unit testing with the manual dispatcher
     */
    public function set_request(midgardmvc_core_request $request)
    {
        $this->request = $request;
    }

    /**
     * Parse request URL into components and return a corresponding MVC request object
     *
     * @return midgardmvc_core_request
     */
    public function get_request()
    {
        return $this->request;
    }

    /**
     * Load a component and dispatch the request to it
     */
    public function dispatch(midgardmvc_core_request $request)
    {
        $route = $request->get_route();
        $argv_str = preg_replace('%/{2,}%', '/', '/' . implode('/', $request->get_arguments()) . '/');
        $query = $request->get_query();
        $arguments = $route->check_match($argv_str, $query);
        if (is_null($arguments))
        {
            throw new midgardmvc_exception_notfound('Selected route ' . $route->id . ' doesn\'t match current URL ' . $request->get_path());
        }

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
            if (!method_exists($controller, $action_method))
            {
                throw new midgardmvc_exception_httperror("{$request_method} method not allowed", 405);
            }
            $controller->data = array();
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
     * @return string url
     */
    public function generate_url($route_id, array $args, $intent)
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

    public function headers_sent()
    {
        return headers_sent();
    }

    public function header($string, $replace = true, $http_response_code = null)
    {
        return;
    }

    public function session_start()
    {
        return;
    }

    public function session_is_started()
    {
        return false;
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
}
?>
