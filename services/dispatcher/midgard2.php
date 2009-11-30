<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Midgard2 dispatcher for MidCOM 3
 *
 * Dispatches Midgard HTTP requests to components.
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_services_dispatcher_midgard2 extends midgardmvc_core_services_dispatcher_midgard implements midgardmvc_core_services_dispatcher
{
    /**
     * Midgard's request configuration object
     */
    private $request_config = null;

    /**
     * Read the request configuration and parse the URL
     */
    public function __construct()
    {
        if (!extension_loaded('midgard2'))
        {
            throw new Exception('Midgard 2.x is required for this MidCOM setup.');
        }

        $this->request_method = $_SERVER['REQUEST_METHOD'];

        $this->request_config = $this->get_midgard_connection()->get_request_config();
        if (!$this->request_config)
        {
            throw new midcom_exception_httperror('Midgard database connection not found.', 503);
        }

        $_argv = $this->request_config->get_argv();
        foreach ($_argv as $argument)
        {
            if (substr($argument, 0, 1) == '?')
            {
                // FIXME: For some reason we get GET parameters into the argv string too, move them to get instead
                // URI (and argv) is built using $_SERVER['REQUEST_URI'].
                // See http://trac.midgard-project.org/ticket/1209
                $url_components = parse_url("http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}");
                if (empty($url_components['query']))
                {
                    break;
                }
                
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
                
                break;
            }
            
            $this->argv[] = $argument;
        }
    }
    
    /**
     * Pull data from currently loaded page into the context.
     */
    public function populate_environment_data()
    {
        $_host = $this->request_config->get_host();
        $prefix = "{$_host->prefix}/";
        foreach ($this->request_config->get_pages() as $page)
        {
            if ($page->id != $_host->root)
            {
                $prefix = "{$prefix}{$page->name}/";
            }
            $current_page = $page;
        }

        $_MIDCOM->context->component = $current_page->component;
        $_MIDCOM->context->uri = '/' . implode('/', $this->argv);
        $_MIDCOM->context->page = $current_page;
        $_MIDCOM->context->prefix = $prefix;
        $_MIDCOM->context->host = $_host;
        $_MIDCOM->context->root = $_host->root;
        $_MIDCOM->context->request_method = $this->request_method;

        $_MIDCOM->context->webdav_request = false;
        if (   $_MIDCOM->configuration->get('enable_webdav')
            && (   $this->request_method != 'GET'
                && $this->request_method != 'POST')
            )
        {
            // Serve this request with the full HTTP_WebDAV_Server
            $_MIDCOM->context->webdav_request = true;
        }
        
        // Append styles from context
        $_MIDCOM->context->style_id = 0;
        $_style = $this->request_config->get_style();
        if ($_style)
        {
            $_MIDCOM->context->style_id = $_style->id;
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
?>
