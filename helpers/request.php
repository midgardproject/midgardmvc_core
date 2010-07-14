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
class midgardmvc_core_helpers_request
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
    private $root_node = null;

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
    
    private $component = 'midgardmvc_core';

    private $path_for_page = array();

    /**
     * URL parameters after page has been resolved
     */
    public $argv = array();

    public function __construct()
    {
    }

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
     * Set a page to be used in the request
     */
    public function set_root_node(midgardmvc_core_providers_hierarchy_node $node)
    {
        $this->root_node = $node;
    }

    /**
     * Set a page to be used in the request
     */
    public function set_node(midgardmvc_core_providers_hierarchy_node $node)
    {
        $this->node = $node;
        $this->set_argv($node->get_arguments());
        $this->set_component($node->get_component());
    }

    public function get_node()
    {
        return $this->node;
    }

    public function set_component($component)
    {
        if (!$component)
        {
            return;
        }
        $this->component = $component;
    }

    public function get_component()
    {
        return $this->component;
    }

    public function set_argv(array $argv)
    {
        $this->argv = $argv;
    }
    
    public function get_argv()
    {
        return $this->argv;
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
    public function generate_identifier()
    {
        $_core = midgardmvc_core::get_instance();
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

    /**
     * Populate request information info the Midgard MVC context
     */
    public function populate_context()
    {
        $_core = midgardmvc_core::get_instance();
        $_core->context->templatedir_id = $this->templatedir_id;
        $_core->context->root_node = $this->root_node;
        $_core->context->component = $this->component;
        
        $_core->context->uri = $this->path;
        $_core->context->self = $this->path;
        $_core->context->node = $this->node;
        $_core->context->prefix = $this->prefix;
        $_core->context->argv = $this->argv;
        $_core->context->query = $this->query;
        $_core->context->request_method = $this->method;
        $_core->context->webdav_request = false;
    }
}
