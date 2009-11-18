<?php
/**
 * @package midcom_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

include MIDCOM_ROOT . "/midcom_core/services/cache.php";

/**
 * null cache backend.
 * 
 * This backend does not perform any I/O operations, and therefore doesn't really cache anything.
 *
 * @package midcom_core
 */
class midcom_core_services_cache_null extends midcom_core_services_cache_base implements midcom_core_services_cache
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