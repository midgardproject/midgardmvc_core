<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Component inheritance chain-based configuration implementation for Midgard MVC
 *
 * Configuration of a request is a single flat array of key-value pairs, merged from a configuration stack
 * in following order:
 *
 * - midgardmvc_core
 * - parent components of current component
 * - current component
 * - injectors
 * - local configuration of a node
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_services_configuration_chain implements midgardmvc_core_services_configuration
{
    static $configuration = array();
    private $local_configuration = array();

    public function __construct(array $local_configuration = null)
    {
        if (!is_null($local_configuration))
        {
            $this->local_configuration = $local_configuration;
        }
        $this->mvc = midgardmvc_core::get_instance();
    }

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
    public function get($key)
    {
        if (array_key_exists($key, $this->local_configuration))
        {
            return $this->local_configuration[$key];
        }

        // Build inheritance chain
        $request = $this->mvc->context->get_request();
        if (!$request)
        {
            $components = array($this->mvc->component->get('midgardmvc_core'));
        }
        else
        {
            $components = array_reverse($request->get_component_chain());
        }
        foreach ($components as $component)
        {
            $component_value = $this->get_from_component($component, $key);
            if (!is_null($component_value))
            {
                return $component_value;
            }
        }

        $components = array($this->mvc->component->get('midgardmvc_core'));
        $component_value = $this->get_from_component($components[0], $key);
        if (!is_null($component_value))
        {
            return $component_value;
        }
        throw new OutOfBoundsException("Configuration key '{$key}' does not exist");
    }

    private function get_from_component(midgardmvc_core_providers_component_component $component, $key)
    {
        if (!isset(self::$configuration[$component->name]))
        {
            self::$configuration[$component->name] = $component->get_configuration();
        }
        if (array_key_exists($key, self::$configuration[$component->name]))
        {
            return self::$configuration[$component->name][$key];
        }
        return null;
    }

    public function __get($key)
    {
        return $this->get($key);
    }

    /**
     * Checks for the existence of a configuration key.
     *
     * @param string $key The configuration key to check for.
     * @return boolean True, if the key is available, false otherwise.
     */
    public function exists($key)
    {
        if (array_key_exists($key, $this->local_configuration))
        {
            return true;
        }
        // Build inheritance chain
        $request = $this->mvc->context->get_request();
        if (!$request)
        {
            $components = array($this->mvc->component->get('midgardmvc_core'));
        }
        else
        {
            $components = array_reverse($request->get_component_chain());
        }
        foreach ($components as $component)
        {
            if (!isset(self::$configuration[$component->name]))
            {
                self::$configuration[$component->name] = $component->get_configuration();
            }
            if (array_key_exists($key, self::$configuration[$component->name]))
            {
                return true;
            }
        }
        return false;
    }

    public function __isset($key)
    {
        return $this->exists($key);
    }
}
?>
