<?php
/**
 * @package midcom_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

include MIDCOM_ROOT . "/midcom_core/services/cache.php";

/**
 * SQLite cache backend.
 * 
 * Backend requires SQLite3 PECL package for PHP
 *
 * @package midcom_core
 */
class midcom_core_services_cache_sqlite3 extends midcom_core_services_cache_base implements midcom_core_services_cache
{
    private $_db;
    private $_table;
    
    public function __construct()
    {
        if (!extension_loaded('sqlite3'))
        {
            throw new Exception('SQLite cache configured but "sqlite3" PHP extension not installed.');
        }

        $this->_db = new SQLite3($this->get_cache_directory() . '/' . $this->get_cache_name() . '.sqlite');
        
        $this->_table = str_replace
        (
            array
            (
                '.', '-'
            ), 
            '_', $this->get_cache_name()
        );
        
        // Check if we have a DB table corresponding to current cache name 
        $result = $this->_db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$this->_table}'");
        $tables = $result->fetchArray();
        if (   count($tables) == 0 
            || $tables == false)
        {
            /**
             * Creating table for data
             */
            $this->_db->query("CREATE TABLE {$this->_table} (module VARCHAR(255), identifier VARCHAR(255), value TEXT);");
            $this->_db->query("CREATE INDEX {$this->_table}_identifier ON {$this->_table} (identifier);");
            $this->_db->query("CREATE INDEX {$this->_table}_module ON {$this->_table} (module);");
            
            /**
             * Creating table for tags
             */
            $this->_db->query("CREATE TABLE {$this->_table}_tags (identifier VARCHAR(255), tag VARCHAR(255));");
            $this->_db->query("CREATE INDEX {$this->_table}_tags_i ON {$this->_table}_tags (identifier, tag);");
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
        if (!isset($_MIDCOM->context->host))
        {
            return 'MidCOM';
        }
        return $_MIDCOM->context->host->name;
    }

    public function put($module, $identifier, $data)
    {
        $module = $this->_db->escapeString($module);
        $identifier = $this->_db->escapeString($identifier);
        $data = $this->_db->escapeString(serialize($data));
        return $this->_db->query("REPLACE INTO {$this->_table} (module, identifier, value) VALUES ('{$module}', '{$identifier}', '{$data}')");
    }
    
    public function get($module, $identifier)
    {
        $module = $this->_db->escapeString($module);
        $identifier = $this->_db->escapeString($identifier);
        $results = $this->_db->query("SELECT value FROM {$this->_table} WHERE module='{$module}' AND identifier='{$identifier}'");
        $results = $results->fetchArray();
        
        if (count($results) == 0)
        {
            return false; // no hit
        }
        
        return unserialize($results[0]);
    }       
    
    public function delete($module, $identifier)
    {
        $key = $this->_db->escapeString($identifier);
        $module = $this->_db->escapeString($module);
        $this->_db->query("DELETE FROM {$this->_table} WHERE module='{$module}' AND identifier='{$identifier}'");
        $this->_db->query("DELETE FROM {$this->_table}_tags WHERE identifier='{$identifier}'");
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
        $this->_db->query("DELETE FROM {$this->_table} WHERE module='{$module}'");
        $this->_db->query("DELETE FROM {$this->_table}_tags WHERE module='{$module}'");
    }
}
?>
