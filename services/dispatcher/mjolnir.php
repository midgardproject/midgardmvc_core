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
        if (!extension_loaded('midgard2'))
        {
            throw new Exception('Midgard 2.x is required for this Midgard MVC setup.');
        }

        $this->midgardmvc = midgardmvc_core::get_instance();

        $this->midgardmvc->load_provider('hierarchy');
        $this->_root_node = $this->midgardmvc->hierarchy->get_root_node();
    }
    
    /**
     * Parse request URL into components and return a corresponding MVC request object
     *
     * @return midgardmvc_core_helpers_request
     */
    public function get_request()
    {
        $request = new midgardmvc_core_helpers_request();
        $request->set_root_node($this->_root_node);
        $request->set_method($_SERVER['REQUEST_METHOD']);
        
        // Parse URL into components (Mjolnir doesn't do this for us)
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
