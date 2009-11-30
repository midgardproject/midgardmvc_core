<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Autoload caching module
 *
 * Provides a way to cache lists of files for a MidCOM execution for the autoloader to use.
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_services_cache_module_autoload
{
    private $midcom = null;

    public function __construct()
    {
        $this->midcom = midgardmvc_core::get_instance();
    }
    
    public function check($identifier)
    {
        return $this->midcom->cache->exists('autoload', $identifier);
    }
    
    public function load($identifier)
    {
        $files = $this->midcom->cache->get('autoload', $identifier);
        foreach ($files as $file)
        {
            require_once($file);
        } 
        return true;
    }
    
    public function store($identifier)
    {
        return $this->midcom->cache->put('autoload', $identifier, $this->midcom->autoloaded_files);;
    }

    /**
     * Remove all cached autoloading lists
     */
    public function invalidate_all()
    {
        $_MIDCOM->cache->delete_all('autoload');
    }
}
?>
