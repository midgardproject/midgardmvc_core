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

    public function __construct()
    {
        $this->_core = midgardmvc_core::get_instance();
    }

    /**
     * Parse request URL into components and return a corresponding MVC request object
     *
     * @return midgardmvc_core_helpers_request
     */
    public function get_request()
    {
        $request = new midgardmvc_core_helpers_request();
        return $request;
    }
    
    public function initialize(midgardmvc_core_helpers_request $request)
    {
        // In main Midgard request we dispatch the component in connection to a page
        $this->midgardmvc->context->component = $request->get_component();
        $this->midgardmvc->context->component_instance = $this->midgardmvc->componentloader->load($this->midgardmvc->context->component, $this->midgardmvc->context->page);
        $this->midgardmvc->templating->prepare_stack($request);
    }
    
    public function get_routes()
    {
        $routes = $this->midgardmvc->configuration->normalize_routes($this->midgardmvc->configuration->get('routes'));
        $this->midgardmvc->context->component_routes = $routes;
        return $this->midgardmvc->context->component_routes;
    }
    
    public function set_route($route_id, array $arguments)
    {
        $this->_core->context->route_id = $route_id;
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
