<?php
/**
 * @package midcom_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Midgard-Mjölnir+ dispatcher for MidCOM 3
 *
 * Dispatches Midgard HTTP requests to components.
 *
 * @package midcom_core
 */
class midcom_core_services_dispatcher_mjolnir extends midcom_core_services_dispatcher_midgard implements midcom_core_services_dispatcher
{
    private $_page_guid = '4a2f5298c09611de9dcf75343667cef6cef6'; // FIXME: set from config
    private $_root_page = null;
    private $_prefix = '';
    private $_pages = array();

    /**
     * Read the request configuration and parse the URL
     */
    public function __construct()
    {
        if (!extension_loaded('midgard2'))
        {
            throw new Exception('Midgard 2.x is required for this MidCOM setup.');
        }
        
        if (!ini_get('midgard.superglobals_compat'))
        {
            throw new Exception('For now you need to set midgard.superglobals_compat=On in your php.ini to run MidCOM3 on Mjolnir');
        }

        $this->request_method = $_SERVER['REQUEST_METHOD'];

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

        $this->_root_page = new midgard_page($this->_page_guid);
        if (!$this->_root_page->guid)
        {
            $this->_root_page = new midgard_page();
            $this->_root_page->get_by_path('/midcom_root');
        }
        $current_page = $this->_root_page;
        $this->_pages[] = $this->_root_page;
        $no_more_pages = false;

        // removing leading slash, or first element after exploding would be empty
        $_argv = explode('/', substr($url_components['path'], 1));
        if (count($_argv) > 0 and $_argv[count($_argv) - 1] == '')
        {
            array_pop($_argv);
        }
        
        foreach ($_argv as $argument)
        {
            if (false === $no_more_pages)
            {
                $_child = $this->get_subpage($current_page, $argument);
                if (null === $_child)
                {
                    $no_more_pages = true;
                    $this->argv[] = $argument;
                    continue;
                }

                $this->_pages[] = $_child;
                $this->_prefix .= "/{$argument}";
                $current_page = $_child;
            }
            else
            {
                $this->argv[] = $argument;
            }
        }
    }

    private function get_subpage($parent, $child_name)
    {
        $q = new midgard_query_builder('midgard_page');
        $q->add_constraint('up', '=', $parent->id);
        $q->add_constraint('name', '=', $child_name);
        $res = $q->execute();

        if (count($res) == 0)
        {
            return null;
        }
        return $res[0];
    }
    
    /**
     * Pull data from currently loaded page into the context.
     */
    public function populate_environment_data()
    {
        $prefix = "{$this->_prefix}/";

        $_core = midcom_core_midcom::get_instance();

        $_core->context->style_id = 0;
        $_core->context->root = $this->_root_page->id;

        foreach ($this->_pages as $page)
        {
            if ($page->id != $this->_root_page->id)
            {
                $prefix .= "{$page->name}/";
            }
            $current_page = $page;

            if ($current_page->style) {
                $_core->context->style_id = $current_page->style;
            }
        }

        $_core->context->component = $current_page->component;
        $_core->context->uri = '/' . implode('/', $this->argv);
        $_core->context->page = $current_page;
        $_core->context->prefix = $prefix;
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
