<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

include MIDGARDMVC_ROOT . "/midgardmvc_core/services/cache.php";

/**
 * SQLite cache backend.
 * 
 * Backend requires SQLite PECL package for PHP
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_services_cache_sqlite extends midgardmvc_core_services_cache_base implements midgardmvc_core_services_cache
{
    private $_db;
    private $_table;
    
    public function __construct()
    {
        if (!extension_loaded('sqlite'))
        {
            throw new Exception('SQLite cache configured but "sqlite" PHP extension not installed.');
        }

        $this->_db = sqlite_open($this->get_cache_directory() . '/' . $this->get_cache_name() . '.sqlite');
        
        $this->_table = str_replace
        (
            array
            (
                '.', '-'
            ), 
            '_', $this->get_cache_name()
        );
        
        // Check if we have a DB table corresponding to current cache name 
        $result = sqlite_query($this->_db, "SELECT name FROM sqlite_master WHERE type='table' AND name='{$this->_table}'");
        $tables = sqlite_fetch_array($result);
        if (   count($tables) == 0 
            || $tables == false)
        {
            /**
             * Creating table for data
             */
            sqlite_query($this->_db, "CREATE TABLE {$this->_table} (module VARCHAR(255), identifier VARCHAR(255), value TEXT);");
            sqlite_query($this->_db, "CREATE INDEX {$this->_table}_identifier ON {$this->_table} (identifier);");
            sqlite_query($this->_db, "CREATE INDEX {$this->_table}_module ON {$this->_table} (module);");
            
            /**
             * Creating table for tags
             */
            sqlite_query($this->_db, "CREATE TABLE {$this->_table}_tags (identifier VARCHAR(255), tag VARCHAR(255));");
            sqlite_query($this->_db, "CREATE INDEX {$this->_table}_tags_i ON {$this->_table}_tags (identifier, tag);");
        }
        
        parent::__construct();
    }

    private function get_cache_directory()
    {
        if (isset($_ENV['MIDGARD_ENV_GLOBAL_CACHEDIR']))
        {
            // Fluid instance has a dynamic cache directory location
            // FIXME: We need to make configuration more dynamic to support this properly
            return $_ENV['MIDGARD_ENV_GLOBAL_CACHEDIR'];
        }
        if (!isset($_MIDGARD))
        {
            return '/var/cache/midgard';
        }
        switch ($_MIDGARD['config']['prefix'])
        {
            case '/usr':
            case '/usr/local':
                return '/var/cache/midgard';
            default:
                return "{$_MIDGARD['config']['prefix']}/var/cache/midgard";
        }
    }

    private function get_cache_name()
    {
        if (!isset(midgardmvc_core::get_instance()->context->host))
        {
            return 'MidCOM';
        }
        return midgardmvc_core::get_instance()->context->host->name;
    }

    public function put($module, $identifier, $data)
    {
        $module = sqlite_escape_string($module);
        $identifier = sqlite_escape_string($identifier);
        $data = sqlite_escape_string(serialize($data));
        return sqlite_query($this->_db, "REPLACE INTO {$this->_table} (module, identifier, value) VALUES ('{$module}', '{$identifier}', '{$data}')");
    }
    
    public function get($module, $identifier)
    {
        $module = sqlite_escape_string($module);
        $identifier = sqlite_escape_string($identifier);
        $results = sqlite_query($this->_db, "SELECT value FROM {$this->_table} WHERE module='{$module}' AND identifier='{$identifier}'");
        $results = sqlite_fetch_array($results);
        
        if (count($results) == 0)
        {
            return false; // no hit
        }
        
        return unserialize($results[0]);
    }       
    
    public function delete($module, $identifier)
    {
        $key = sqlite_escape_string($identifier);
        $module = sqlite_escape_string($module);
        sqlite_query($this->_db, "DELETE FROM {$this->_table} WHERE module='{$module}' AND identifier='{$identifier}'");
        sqlite_query($this->_db, "DELETE FROM {$this->_table}_tags WHERE identifier='{$identifier}'");
    }
    
    public function exists($module, $identifier)
    {
        if( $this->get($module, $identifier) == false)
        {
            return false;
        }
        return true;
    }
    
    public function delete_all($module)
    {
        sqlite_query($this->_db, "DELETE FROM {$this->_table} WHERE module='{$module}'");
        sqlite_query($this->_db, "DELETE FROM {$this->_table}_tags WHERE module='{$module}'");
    }
}
?>
