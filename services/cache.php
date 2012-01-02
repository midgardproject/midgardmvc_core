<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Cache interface for Midgard MVC
 *
 * @package midgardmvc_core
 */
interface midgardmvc_core_services_cache
{
    /**
     * Register an array of tags to all caches
     *
     * @param string $cache_id
     * @param array $tags
     */
    public function register($identifier, array $tags);
    
    /**
     * Invalidate an array of tags from all caches
     *
     * @param array $tags
     */
    public function invalidate(array $tags);
    
    /**
     * Invalidate everything from all caches
     */
    public function invalidate_all();
    
    /**
     * Put data into a module's cache
     *
     * This method is to be utilized by the cache modules.
     *
     * @param string $module Module name
     * @param string $identifier Cache identifier to store to
     * @param mixed $data Data to store (may be serialized by storage backend)
     */
    public function put($module, $identifier, $data);
    
    /**
     * Get data from module's cache
     *
     * This method is to be utilized by the cache modules.
     *
     * @param string $module Module name
     * @param string $identifier Cache identifier to store to
     * @return mixed Stored data
     */
    public function get($module, $indentifier);

    /**
     * Remove data from module's cache
     *
     * This method is to be utilized by the cache modules.
     *
     * @param string $module Module name
     * @param string $identifier Cache identifier to store to
     */
    public function delete($module, $identifier);

    /**
     * Check if module's cache has data for a given identifier
     *
     * This method is to be utilized by the cache modules.
     *
     * @param string $module Module name
     * @param string $identifier Cache identifier to store to
     * @return boolean
     */
    public function exists($module, $identifier);

    /**
     * Remove all data from a module's cache
     *
     * This method is to be utilized by the cache modules.
     *
     * @param string $module Module name
     */
    public function delete_all($module);
}

abstract class midgardmvc_core_services_cache_base
{
    private $modules = array();
    private $configuration = array();

    public function __construct()
    {
        $this->configuration = midgardmvc_core::get_instance()->configuration->services_cache_configuration;

        // Move these values to context so modules and components can manipulate them as needed
        $request = midgardmvc_core::get_instance()->context->get_request();
        if (!$request)
        {
            return;
        }
        $request->set_data_item('cache_expiry', $this->configuration['expiry']);
        $request->set_data_item('cache_strategy', $this->configuration['strategy']);
        $request->set_data_item('cache_enabled', $this->configuration['enabled']);

        if ($request->get_data_item('cache_enabled'))
        {
            $mgdschemas = midgardmvc_core::get_instance()->dispatcher->get_mgdschema_classes();
            foreach ($mgdschemas as $mgdschema)
            {
                $this->connect_to_signals($mgdschema);
            }
        }
    }

    private function connect_to_signals($class)
    {
        // Subscribe to the "after the fact" signals
        midgard_object_class::connect_default($class, 'action-loaded', array($this, 'register_object'), array($class));
        midgard_object_class::connect_default($class, 'action-update', array($this, 'invalidate_object'), array($class));
        midgard_object_class::connect_default($class, 'action-delete', array($this, 'invalidate_object'), array($class));
    }

    public function register_object($object, $params = null)
    {
        if (!isset(midgardmvc_core::get_instance()->context->cache_request_identifier))
        {
            return;
        }

        // Register loaded objects to content cache
        midgardmvc_core::get_instance()->cache->content->register(midgardmvc_core::get_instance()->context->cache_request_identifier, array($object->guid));
    }

    /**
     * Invalidate a given object GUID from all caches
     *
     * This should be called when object has been updated or deleted for instance.
     */
    public function invalidate_object($object, $params = null)
    {
        midgardmvc_core::get_instance()->cache->invalidate(array($object->guid));
    }

    /**
     * Helper for module initialization. Usually called via getters
     *
     * @param string $module Name of module to load
     */
    private function load_module($module)
    {
        if (isset($this->modules[$module]))
        {
            return;
        }
        
        $module_file = midgardmvc_core::get_component_path('midgardmvc_core') . "/services/cache/module/{$module}.php";
        if (!file_exists($module_file))
        {
            throw new Exception("Cache module {$module} not installed");
        }

        $module_class = "midgardmvc_core_services_cache_module_{$module}";
        $module_config = array();
        if (isset($this->configuration["module_{$module}"]))
        {
            $module_config = $this->configuration["module_{$module}"];
        }

        $this->modules[$module] = new $module_class($module_config);
    }

    /**
     * Magic getter for module loading
     */
    public function __get($module)
    {
        $this->load_module($module);
        return $this->modules[$module];
    }

    public function register($identifier, array $tags)
    {
        $this->prepare_modules();
        foreach ($this->modules as $module)
        {
            $module->register($identifier, $tags);
        }
    }

    public function invalidate(array $tags)
    {
        $this->prepare_modules();
        foreach ($this->modules as $module)
        {
            $module->invalidate($tags);
        }
    }

    public function invalidate_all()
    {
        $this->prepare_modules();
        foreach ($this->modules as $module)
        {
            $module->invalidate_all();
        }
        // Manifest caching doesn't have a module of its own
        midgardmvc_core::get_instance()->cache->delete_all('manifest');
    }
    
    private function prepare_modules()
    {
        // Ensure all modules are loaded
        $this->load_module('content');
        $this->load_module('template');        
    }
}
