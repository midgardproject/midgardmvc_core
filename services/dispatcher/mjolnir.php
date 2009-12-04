<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Midgard-Mjölnir+ dispatcher for Midgard MVC
 *
 * Dispatches Midgard HTTP requests to components.
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_services_dispatcher_mjolnir extends midgardmvc_core_services_dispatcher_midgard implements midgardmvc_core_services_dispatcher
{
    private $_root_page = null;
    private $_pages = array();

    /**
     * Read the request configuration and parse the URL
     */
    public function __construct()
    {
        if (!extension_loaded('midgard2'))
        {
            throw new Exception('Midgard 2.x is required for this Midgard MVC setup.');
        }

        $this->request_method = $_SERVER['REQUEST_METHOD'];
        
        // Populate GET params
        $url_components = parse_url("http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}");
        if (!empty($url_components['query']))
        {
            $query_items = explode('&', $url_components['query']);
            foreach ($query_items as $query_item)
            {
                $query_pair = explode('=', $query_item);
                if (count($query_pair) != 2)
                {
                    break;
                }
                $this->get[$query_pair[0]] = urldecode($query_pair[1]);
            }
        }

        $this->midgardmvc = midgardmvc_core::get_instance();

        $this->_root_page = new midgard_page($this->midgardmvc->configuration->midgardmvc_root_page);
        if (!$this->_root_page->guid)
        {
            $this->_root_page = new midgard_page();
            $this->_root_page->get_by_path('/midcom_root');
        }
        
        $this->request = new midgardmvc_core_helpers_request();
        $this->request->set_root_page($this->_root_page);
        $this->page = $this->request->resolve_page($url_components['path']);
        $this->argv = $this->request->argv;
    }
    
    /**
     * Pull data from currently loaded page into the context.
     */
    public function populate_environment_data()
    {
        $_core = midgardmvc_core::get_instance();

        $_core->context->style_id = $this->request->style_id;
        $_core->context->root = $this->_root_page->id;
        $_core->context->component = $this->page->component;
        
        $_core->context->uri = $this->request->path;
        if (!empty($this->argv))
        {
            $_core->context->uri .= implode('/', $this->argv) . '/';
        }

        $_core->context->self = $this->request->path;
        $_core->context->page = $this->page;
        $_core->context->prefix = '/';
        $_core->context->argv = $this->argv;
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
