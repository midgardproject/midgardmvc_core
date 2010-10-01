<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Midgard MVC HTTP request and URL mapping helper
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_request
{
    /**
     * HTTP verb used with request
     */
    private $method = 'get';

    /**
     * HTTP query parameters used with request
     */
    private $query = array();

    /**
     * The root page to be used with the request
     *
     * @var midgardmvc_core_providers_hierarchy_node
     */
    private static $root_node = null;

    /**
     * The page to be used with the request
     *
     * @var midgardmvc_core_providers_hierarchy_node
     */
    private $node = null;

    /**
     * Midgard templatedir to use with the request
     */
    public $templatedir_id = 0;

    /**
     * Path to the page used with the request
     */
    public $path = '/';

    private $prefix = '/';
    
    /**
     * The primary component for the request
     *
     * @var midgardmvc_core_providers_component_component
     */
    private $component = null;

    /**
     * List of components affecting merging chains of this request
     */
    private $components = array();

    private $path_for_page = array();

    private $route = null;

    private $template_aliases = array();

    /**
     * URL parameters after page has been resolved
     */
    public $argv = array();

    /**
     * Data associated with the request, typically set by a controller and displayed by a template
     */
    private $data = array();

    /**
     * Match an URL path to a page. Remaining path arguments are stored to argv
     *
     * @param $path URL path
     */
    public function resolve_node($path)
    {
        $node = midgardmvc_core::get_instance()->hierarchy->get_node_by_path($path);
        $this->set_node($node);
    }

    /**
     * Get the request path
     */
    public function get_path()
    {
        if (substr($this->path, -1, 1) !== '/')
        {
            $this->path .= '/';
        }
        return $this->path . implode('/', $this->argv);
    }

    /**
     * Set a page to be used in the request
     */
    public function set_root_node(midgardmvc_core_providers_hierarchy_node $node)
    {
        self::$root_node = $node;
    }

    /**
     * Get root node used in this request
     */
    public function get_root_node()
    {
        return self::$root_node;
    }

    /**
     * Set a page to be used in the request
     */
    public function set_node(midgardmvc_core_providers_hierarchy_node $node)
    {
        $this->node = $node;
        $this->set_arguments($node->get_arguments());
        $node_component = $node->get_component();
        if (!$node_component)
        {
            $node_component = 'midgardmvc_core';
        }
        $this->set_component(midgardmvc_core::get_instance()->component->get($node_component));

        $this->path = $node->get_path();
    }

    public function get_node()
    {
        return $this->node;
    }

    public function get_component_chain()
    {
        return $this->components;
    }

    public function add_component_to_chain(midgardmvc_core_providers_component_component $component)
    {
        $components_array = array
        (
            $component,
        );
        while (true)
        {
            $component = $component->get_parent();
            if (is_null($component))
            {
                break;
            }
            if (isset($this->components[$component->name]))
            {
                // We have this part of the chain already
                break;
            }
            $components_array[] = $component;
        }
        $components_array = array_reverse($components_array);
        foreach ($components_array as $component)
        {
            $this->components[$component->name] = $component;
        }
    }

    public function set_component(midgardmvc_core_providers_component_component $component)
    {
        if (!$component)
        {
            return;
        }
        $this->component = $component;

        $this->add_component_to_chain($component);
    }

    public function get_component()
    {
        return $this->component;
    }

    public function set_route(midgardmvc_core_route $route)
    {
        $this->route = $route;
    }

    public function get_route()
    {
        return $this->route;
    }

    public function set_arguments(array $argv)
    {
        $this->argv = $argv;
    }
    
    public function get_arguments()
    {
        return $this->argv;
    }

    public function set_template_alias($alias, $template)
    {
        $this->template_aliases[$alias] = $template;
    }

    public function get_template_alias($alias)
    {
        if (!isset($this->template_aliases[$alias]))
        {
            return $alias;
        }
        return $this->template_aliases[$alias];
    }

    public function set_data_item($key, $value)
    {
        // TODO: These are deprecated keys that used to be populated to context
        switch ($key)
        {
            case 'root_node':
            case 'root_page':
                return $this->set_root_node($value);
            case 'node':
            case 'page':
                return $this->set_node($value);
            case 'prefix':
                return $this->set_prefix($value);
            case 'argv':
                return $this->set_arguments($value);
            case 'query':
                return $this->set_query($value);
            case 'request_method':
                return $this->set_method($value);
            default:
                $this->data[$key] = $value;
                break;
        }
    }

    public function isset_data_item($key)
    {
        return isset($this->data[$key]);
    }

    public function get_data_item($key)
    {
        if (!isset($this->data[$key]))
        {
            // TODO: These are deprecated keys that used to be populated to context
            switch ($key)
            {
                case 'root_node':
                case 'root_page':
                    return $this->get_root_node();
                case 'component':
                    return $this->component;
                case 'uri':
                    return $this->path;
                case 'self':
                    return $this->prefix;
                case 'node':
                case 'page':
                    return $this->node;
                case 'prefix':
                    return $this->prefix;
                case 'argv':
                    return $this->argv;
                case 'query':
                    return $this->query;
                case 'request_method':
                    return $this->method;
                default:
                    throw new OutOfBoundsException("Midgard MVC request data '{$key}' not found.");
            }
        }
        return $this->data[$key];
    }

    public function get_data()
    {
        return $this->data;
    }

    public function set_method($method)
    {
        $this->method = strtolower($method);
    }
    
    public function get_method()
    {
        return $this->method;
    }
    
    public function set_query(array $get_params)
    {
        $this->query = $get_params;
    }
    
    public function get_query()
    {
        return $this->query;
    }

    public function set_prefix($prefix)
    {
        $this->prefix = $prefix;
    }
    
    public function get_prefix()
    {
        return $this->prefix;
    }

    /**
     * Generate a valid cache identifier for a context of the current request
     */
    public function get_identifier()
    {
        if (isset($this->data['cache_request_identifier']))
        {
            // An injector has generated this already, let it be
            return $this->data['cache_request_identifier'];
        }

        $identifier_source  = 'URI=' . $this->get_path();
        $identifier_source .= ";COMP={$this->component->name}";
        
        // TODO: Check language settings
        $identifier_source .= ';LANG=ALL';

        // Template info too
        if (isset($this->data['template_entry_point']))
        {
            $identifier_source .= ';TEMPLATE=' . $this->get_data_item('template_entry_point');
            $identifier_source .= ';CONTENT=' . $this->get_data_item('content_entry_point');
        }
        
        if (   isset($this->data['cache_enabled'])
            && $this->data['cache_enabled'])
        {
            switch ($this->data['cache_strategy'])
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
        }

        $this->data['cache_request_identifier'] = md5($identifier_source);
        return $this->data['cache_request_identifier'];
    }

    /**
     * Get a request object for an intent
     *
     * Intent may be one of:
     * - Instance of a Midgard MVC node (of hierarchy provider)
     * - Instance of a Midgard MVC node (of MgdSchema)
     * - Component name
     * - Path
     *
     * @args mixed $intent Component name, node object, node GUID or node path
     */
    public static function get_for_intent($intent)
    {
        $request = new midgardmvc_core_request();
        if (mgd_is_guid($intent))
        {
            // MgdSchema node GUID given
            $intent = new midgardmvc_core_node($intent);
        }
        if (is_object($intent))
        {
            if ($intent instanceof midgardmvc_core_request)
            {
                $request = $intent;
            }

            if ($intent instanceof midgardmvc_core_node)
            {
                // Change the MgdSchema object to a hierarchy node
                $intent = new midgardmvc_core_providers_hierarchy_node_midgardmvc($intent);
            }
            
            if ($intent instanceof midgardmvc_core_providers_hierarchy_node)
            {
                $request->set_node($intent);
            }
        }
        elseif (strpos($intent, '/') !== false)
        {
            // Path-based intent
            $request->resolve_node($intent);
        }
        else
        {
            // Component name -based intent
            // Try to find node matching the component
            $node = midgardmvc_core::get_instance()->hierarchy->get_node_by_component($intent);
            if (is_null($node))
            {
                // Instanceless component
                $component = midgardmvc_core::get_instance()->component->get($intent);
                $request->set_component($component);
            }
            else
            {
                // Found instance, set it for the request
                $request->set_node($node);
            }
        }

        return $request;
    }
}
