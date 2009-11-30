<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

include MIDGARDMVC_ROOT . "/midgardmvc_core/services/cache.php";

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
    private $_db;
    private $_table;
    
    public function __construct()
    {
        parent::__construct();
    }

    public function put($module, $identifier, $data)
    {
        if (!isset($this->_core->context->host))
        {
            return;
        }
        $this->_core->context->host->set_parameter("midgardmvc_core_services_cache_midgard:{$module}", $identifier, serialize($data));
    }

    public function get($module, $identifier)
    {
        if (!isset($this->_core->context->host))
        {
            return;
        }
        $data = $this->_core->context->host->get_parameter("midgardmvc_core_services_cache_midgard:{$module}", $identifier);
        if (!$data)
        {
            return;
        }
        return unserialize($data);
    }

    public function delete($module, $identifier)
    {
        if (!isset($this->_core->context->host))
        {
            return;
        }
        $this->_core->context->host->set_parameter("midgardmvc_core_services_cache_midgard:{$module}", $identifier, '');
    }

    public function exists($module, $identifier)
    {
        if (!isset($this->_core->context->host))
        {
            return false;
        }
        if (is_null ($this->get($module, $identifier)))
        {
            return false;
        }
        return true;
    }

    public function delete_all($module)
    {
        if (!isset($this->_core->context->host))
        {
            return;
        }
        $args = array('domain' => "midgardmvc_core_services_cache_midgard:{$module}");
        $this->_core->context->host->delete_parameters($args);
    }    
}
?>
