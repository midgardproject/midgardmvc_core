<?php
/**
 * @package midcom_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Manual dispatcher for MidCOM 3
 *
 * Dispatches requested route and controller of components.
 *
 * @package midcom_core
 */
class midcom_core_services_dispatcher_manual implements midcom_core_services_dispatcher
{
    public $component_name = '';
    public $component_instance = false;
    private $page = null;
    protected $route_id = false;
    protected $action_arguments = array();
    public $request_method = 'GET';

    public function __construct()
    {
        $this->_core = midcom_core_midcom::get_instance();

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
        }

        $this->_core->context->cache_enabled = $this->_core->configuration->services_cache_configuration['enabled'];

        if (!$this->page)
        {
            $this->_core->context->component = $this->component_name;
            return;
        }
           
        $this->_core->context->uri = $this->get_page_prefix();        
        $this->_core->context->component = $this->page->component;
        $this->_core->context->page = $this->page;
        
        if ($this->page->style)
        {
            $this->_core->context->style_id = $this->page->style;
        }
        
        $this->_core->context->prefix = $this->get_page_prefix();
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
        $core_routes = $this->_core->configuration->get('routes');
        $component_routes = $this->_core->context->component_instance->configuration->get('routes');
        
        return array_merge($core_routes, $component_routes);
    }
    
    public function set_page(midgard_page $page)
    {
        $this->page = $page;
    }

    public function resolve_page($path)
    {
        $temp = trim($path);
        
        if (isset($this->_core->context->host))
        {
            $parent_id = $this->_core->context->host;
        }
        else
        {
            $parent_id = 0;
        }
        $this->page_id = $parent_id;
        $path = explode('/', trim($path));
        foreach ($path as $p)
        {
            if (strlen(trim($p)) == 0)
            {                
                continue;
            }
            $qb = new midgard_query_builder('midgard_page');
            $qb->add_constraint('up', '=', $parent_id);
            $qb->add_constraint('name', '=', $p);
            $res = $qb->execute();
            if(count($res) != 1)
            {
                break;            
            }
            $parent_id = $res[0]->id;
            $temp = substr($temp, 1 + strlen($p));
            $page = $res[0];
        }

        if (strlen($temp) < 2)
        {
            $this->path = '/';
        }
        
        return $page;
    }

    private function get_page_prefix()
    {
        if (!$this->page)
        {
            throw new Exception("No page set for the manual dispatcher");
        }
        
        if (!isset($_MIDGARD['host']))
        {
            return null;
        }
    
        if (isset ($_MIDGARD['prefix']))
        {
            $prefix = "{$_MIDGARD['prefix']}/";
        }
        else
        {
            $prefix = '';
        }

        $host_mc = midgard_host::new_collector('id', $_MIDGARD['host']);
        $host_mc->set_key_property('root');
        $host_mc->execute();
        $roots = $host_mc->list_keys();
        if (!$roots)
        {
            throw new Exception("Failed to load root page data for host");
        }
        $root_id = null;
        foreach ($roots as $root => $array)
        {
            $root_id = $root;
            break;
        }
        
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
                        throw new midcom_exception_notfound("Action {$selected_route_configuration['action']} not found");
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
                throw new midcom_exception_httperror("{$this->request_method} not allowed", 405);
            }
        }
        else
        {
            $controller->data =& $data;
            $controller->$action_method($this->action_arguments);
        }

        if ($this->is_core_route($this->route_id))
        {
            $component_name = 'midcom_core';
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
