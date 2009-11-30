<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Log file writing helper for MidCOM 3
 *
 * NOTE: This will be deprecated as soon as the midgard_error methods are backported to Ragnaroek.
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_helpers_log
{
    private $log_file = '';
    
    function __construct($log_name = 'midcom')
    {
        if (!extension_loaded('midgard'))
        {
            throw new Exception('The logging helper is to be used only with Midgard 1.x.');
        }

        $this->log_file = str_replace('__MIDGARDLOG__', $this->get_log_directory(), $_MIDCOM->configuration->get('log_file'));
        
        if (isset($_SERVER['SERVER_NAME']))
        {
            $this->log_file = str_replace('__SERVERNAME__', $_SERVER['SERVER_NAME'], $this->log_file);
        }
        else
        {
            $this->log_file = str_replace('__SERVERNAME__', 'CLI', $this->log_file);            
        }
        
        $this->log_file = str_replace('__LOGNAME__', $log_name, $this->log_file);
        
        if (   !is_writable($this->log_file)
            && !is_writable(dirname($this->log_file)))
        {
            throw new Exception("Log file {$this->log_file} is not writable");
        }
    }

    /**
     * Write a string to the log file
     *
     * @param string $string The message to write to log
     */
    public function log($string, $addtime = true) 
    {
        $log_file = fopen($this->log_file, 'a');
        if (!$log_file) 
        {
            throw new Exception("Log file {$this->log_file} is not writable");
        }
        
        if ($addtime)
        {
            $string = date('r') . ": {$string}";
        }
        
        fwrite($log_file, "{$string}\n");
        fclose($log_file);
    }

    private function get_log_directory()
    {
        if (!isset($_MIDGARD))
        {
            return '/var/log/midgard';
        }

        switch ($_MIDGARD['config']['prefix'])
        {
            case '/usr':
            case '/usr/local':
                return '/var/log/midgard';
            default:
                return "{$_MIDGARD['config']['prefix']}/var/log/midgard";
        }
    }
}
?>