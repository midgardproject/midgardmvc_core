<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

require_once MIDGARDMVC_ROOT . "/midgardmvc_core/services/cache.php";

/**
 * Midgard cache backend.
 *
 * This cache backend stores cached data to host's parameter
 * Primary use for the backend is for testing and developing purposes
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_services_cache_midgard extends midgardmvc_core_services_cache_base implements midgardmvc_core_services_cache
{
    private $cache_object = null;

    public function __construct()
    {
        parent::__construct();
        $this->cache_object = midgardmvc_core::get_instance()->hierarchy->get_root_node()->get_object();
    }

    public function put($module, $identifier, $data)
    {
        return;
        $this->cache_object->set_parameter("midgardmvc_core_services_cache_midgard:{$module}", $identifier, serialize($data));
    }

    public function get($module, $identifier)
    {
        return;
        $data = $this->cache_object->get_parameter("midgardmvc_core_services_cache_midgard:{$module}", $identifier);
        if (!$data)
        {
            return;
        }
        return unserialize($data);
    }

    public function delete($module, $identifier)
    {
        $this->cache_object->set_parameter("midgardmvc_core_services_cache_midgard:{$module}", $identifier, '');
    }

    public function exists($module, $identifier)
    {
        if (is_null($this->get($module, $identifier)))
        {
            return false;
        }
        return true;
    }

    public function delete_all($module)
    {
        $args = array('domain' => "midgardmvc_core_services_cache_midgard:{$module}");
        $this->cache_object->delete_parameters($args);
    }    
}
?>
