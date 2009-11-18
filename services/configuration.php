<?php
/**
 * @package midcom_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Configuration interface for MidCOM 3
 *
 * @package midcom_core
 */
interface midcom_core_services_configuration
{
    /**
     * Loads the configuration system for a given component
     *
     * @param string $component Component to load configuration for
     */
    public function __construct($component, midgard_page $folder = null);

    /**
     * Retrieve a configuration key
     *
     * If $key exists in the configuration data, its value is returned to the caller.
     * If the value does not exist, an exception will be raised.
     *
     * @param string $key The configuration key to query.
     * @return mixed Its value
     * @see midcom_helper_configuration::exists()
     */
    public function get($key, $subkey = false);

    /**
     * @see midcom_helper_configuration::get()
     */
    public function __get($key);

    /**
     * Checks for the existence of a configuration key.
     *
     * @param string $key The configuration key to check for.
     * @return boolean True, if the key is available, false otherwise.
     */
    public function exists($key);

    /**
     * @see midcom_helper_configuration::exists()
     */
    public function __isset($key);

    /**
     * Parses configuration string and returns it in configuration array format
     *
     * @param string $configuration Configuration string
     * @return array The loaded configuration array
     */
    public function unserialize($configuration);
    
    /**
     * Dumps configuration array and returns it as a string
     *
     * @param array $configuration Configuration array     
     * @return string Configuration in string format
     */
    public function serialize(array $configuration);
}
?>