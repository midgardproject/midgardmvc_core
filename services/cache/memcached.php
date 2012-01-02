<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

include midgardmvc_core::get_component_path('midgardmvc_core') . '/services/cache.php';

/**
 * memcached cache backend.
 * 
 * Backend requires Memcache PECL package for PHP, and memcached to be running.
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_services_cache_memcached extends midgardmvc_core_services_cache_base implements midgardmvc_core_services_cache
{
    private $memcache;
    private $name;
    private $memcache_operational = false;
    
    public function __construct()
    {
        if (!extension_loaded('memcache'))
        {
            throw new Exception('memcached cache configured but "Memcache" PHP extension not installed.');
        }
        $this->memcache = new Memcache();
        $this->memcache_operational = @$this->memcache->pconnect('localhost', 11211);
        
        if (!isset(midgardmvc_core::get_instance()->context->host))
        {
            $this->name = 'MidgardMVC';
        }
        else
        {
            $this->name = midgardmvc_core::get_instance()->context->host->name;
        }

        parent::__construct();
    }

    public function put($module, $identifier, $data)
    {
        if (!$this->memcache_operational)
        {
            return;
        }
        $this->memcache->set("{$this->name}-{$module}-{$identifier}", $data);
    }
    
    public function get($module, $identifier)
    {
        if (!$this->memcache_operational)
        {
            return;
        }
        return $this->memcache->get("{$this->name}-{$module}-{$identifier}");
    }       
    
    public function delete($module, $identifier)
    {
        if (!$this->memcache_operational)
        {
            return;
        }
        return $this->memcache->delete("{$this->name}-{$module}-{$identifier}");
    }
    
    public function exists($module, $identifier)
    {
        if (!$this->memcache_operational)
        {
            return;
        }
        return ($this->get($module, $identifier) !== false);
    }
    
    public function delete_all($module)
    {
        if (!$this->memcache_operational)
        {
            return;
        }
        $this->memcache->flush();
    }
}
?>
