<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Configuration interface for Midgard MVC
 *
 * @package midgardmvc_core
 */
interface midgardmvc_core_services_configuration
{
    /**
     * Retrieve a configuration key
     *
     * If $key exists in the configuration data, its value is returned to the caller.
     * If the value does not exist, an exception will be raised.
     *
     * @param string $key The configuration key to query.
     * @return mixed Its value
     * @see midgardmvc_helper_configuration::exists()
     */
    public function get($key);

    /**
     * @see midgardmvc_helper_configuration::get()
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
     * @see midgardmvc_helper_configuration::exists()
     */
    public function __isset($key);
}
?>
