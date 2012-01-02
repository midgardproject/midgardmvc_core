<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

include midgardmvc_core::get_component_path('midgardmvc_core') . '/services/cache.php';

/**
 * null cache backend.
 * 
 * This backend does not perform any I/O operations, and therefore doesn't really cache anything.
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_services_cache_null extends midgardmvc_core_services_cache_base implements midgardmvc_core_services_cache
{
    public function __construct()
    {
        parent::__construct();
    }

    public function put($module, $identifier, $data)
    {
        return true;
    }
    
    public function get($module, $identifier)
    {
        return;
    }       
    
    public function delete($module, $identifier)
    {
        return true;
    }
    
    public function exists($module, $identifier)
    {
        return false;
    }
    
    public function delete_all($module)
    {
        return true;
    }
}
?>
