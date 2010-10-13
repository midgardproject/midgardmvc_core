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
     * The node to be used with the request
     *
     * @var midgardmvc_core_providers_hierarchy_node
     */
    private $node = null;

    /**
     * Midgard templatedir to use with the request
     */
    public $templatedir_id = 0;

    /**
     * Path to the node used with the request
     */
    private $prefix = '/';
    
    /**
     * The primary component for the request
     *
     * @var midgardmvc_core_providers_component_component
     */
    private $component = null;

    /**
     * List of components affecting merging chains of this request
     *
     * @var array
     */
    private $components = array();

    /**
     * Route used with the request
     */
    private $route = null;

    /**
     * URL parameters after page has been resolved
     */
    public $argv = array();

    /**
     * Data associated with the request, typically set by a controller and displayed by a template
     */
    private $data = array();

    private $cache_identifier = null;

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
        $path = $this->prefix . implode('/', $this->argv);
        if (substr($path, -1, 1) !== '/')
        {
            $path .= '/';
        }
        return $path;
    }

    /**
     * Set a root node to be used in the request
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
     * Set a node to be used in the request
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

        $this->prefix = $node->get_path();

        // Clear cache identifier
        $this->cache_identifier = null;
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

        // Clear cache identifier
        $this->cache_identifier = null;
    }

    public function get_component()
    {
        return $this->component;
    }

    public function set_route(midgardmvc_core_route $route)
    {
        $this->route = $route;

        // Clear cache identifier
        $this->cache_identifier = null;
    }

    public function get_route()
    {
        return $this->route;
    }

    public function set_arguments(array $argv)
    {
        $this->argv = $argv;

        // Clear cache identifier
        $this->cache_identifier = null;
    }
    
    public function get_arguments()
    {
        return $this->argv;
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
            case 'self':
                return $this->set_prefix($value);
            case 'uri':
                return;
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
                    return $this->get_component();
                case 'uri':
                    return $this->get_path();
                case 'self':
                case 'prefix':
                    return $this->get_prefix();
                case 'node':
                case 'page':
                    return $this->get_node();
                case 'argv':
                    return $this->get_arguments();
                case 'query':
                    return $this->get_query();
                case 'request_method':
                    return $this->get_method();
                case 'cache_request_identifier':
                    return $this->get_identifier();
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

    /**
     * Set HTTP verb used with the request
     */
    public function set_method($method)
    {
        // HTTP verbs defined by HTTP/1.1 and WebDAV
        $verbs = array
        (
            // Safe methods, should not modify data
            'head',
            'get',
            'trace',
            'options',
            // Idempotent methods, multiple identical requests should produce same effect
            'put',
            'delete',
            // Other HTTP methods
            'post',
            'connect',
            'patch',
            // WebDAV methods
            'propfind',
            'proppatch',
            'mkcol',
            'copy',
            'move',
            'lock',
            'unlock',
        );

        $method = strtolower($method);
        if (!in_array($method, $verbs))
        {
            throw new InvalidArgumentException('Unrecognized HTTP method defined');
        }

        $this->method = $method;
    }
    
    /**
     * Get HTTP verb used with the request
     * Note: HTTP verbs are provided in lowercase format (i.e. get, post)
     */
    public function get_method()
    {
        return $this->method;
    }
    
    /**
     * Set HTTP query arguments ($_GET array in PHP terms) used with the request
     */
    public function set_query(array $get_params)
    {
        $this->query = $get_params;
    }

    /**
     * Get HTTP query arguments ($_GET array in PHP terms) used with the request
     */
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
        if (!is_null($this->cache_identifier))
        {
            // An injector has generated this already, let it be
            return $this->cache_identifier;
        }

        $identifier_source  = 'URI=' . $this->get_path();
        $identifier_source .= ";COMP={$this->component->name}";
        
        // TODO: Check language settings
        $identifier_source .= ';LANG=ALL';

        // Template info too
        if ($this->route)
        {
            $identifier_source .= ';TEMPLATE=' . $this->route->template_aliases['root'];
            $identifier_source .= ';CONTENT=' . $this->route->template_aliases['content'];
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

        $this->cache_identifier = md5($identifier_source);
        return $this->cache_identifier;
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
        if (empty($intent))
        {
            throw new InvalidArgumentException("No intent provided");
        }
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
