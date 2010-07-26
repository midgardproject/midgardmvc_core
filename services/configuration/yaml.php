<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * YAML-based configuration implementation for Midgard MVC
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
class midgardmvc_core_services_configuration_yaml implements midgardmvc_core_services_configuration
{
    static $configuration = array();

    static $injectors = array();

    static $use_yaml = null;
    
    public function __construct()
    {
        if (is_null(self::$use_yaml))
        {
            // Check for YAML extension
            self::$use_yaml = extension_loaded('yaml');
            if (!self::$use_yaml)
            {
                // YAML PHP extension is not loaded, include the pure-PHP implementation
                require_once MIDGARDMVC_ROOT. '/midgardmvc_core/helpers/spyc.php';
            }
        }

        $this->mvc = midgardmvc_core::get_instance();
    }

    public function load_array_to_component($component, array $config)
    {
        if (!isset(self::$configuration[$component]))
        {
            self::$configuration[$component] = $config;
            return;
        }

        self::$configuration[$component] = self::merge_configs(self::$configuration[$component], $config);
    }

    /**
     * Load configuration for a Midgard MVC component and place it to the configuration stack
     */
    public function load_component($component, $injector = false)
    {
        if (isset(self::$configuration[$component]))
        {
            $config = self::$configuration[$component];
            return;
        }
        else
        {
            // Check for component inheritance
            if (isset($this->mvc->componentloader))
            {
                $components = $this->mvc->componentloader->get_tree($component);
            }
            else
            {
                $components = array
                (
                    $component,
                );
            }
            $config = array();
            foreach ($components as $load_component)
            {
                if (isset(self::$configuration[$load_component]))
                {
                    // We already have this component and its parents, no need to traverse further
                    $config = self::merge_configs(self::$configuration[$load_component], $config);
                    break;
                }
                
                // Load component default config from filesystem 
                $component_config = $this->load_file(MIDGARDMVC_ROOT . "/{$load_component}/configuration/defaults.yml");
                $config = self::merge_configs($component_config, $config);
                self::$configuration[$load_component] = $config;
            }
        }

        if ($injector)
        {
            self::$injectors[] = $component;
        }
    }

    public static function merge_configs(array $base, array $extension)
    {
        if (empty($base))
        {
            // Original was empty, no need to merge
            return $extension;
        }
        elseif (empty($extension))
        {
            return $base;
        }
        $merged = $base;
        
        foreach ($extension as $key => $value)
        {
            if (is_array($value)) 
            {
                if (   !isset($merged[$key])
                    || empty($merged[$key])) 
                {
                    // Original was empty, no need to merge
                    $merged[$key] = $value;
                    continue;
                }
                
                if ($key == 'routes')
                {
                    /* 
                     * Routes have special handling to ensure routes from current component
                     * and injectors are run before core routes.
                     *
                     * Routes also don't merge but instead override fully.
                     */
                    $merged[$key] = array();
                    foreach ($extension[$key] as $route_id => $route_definition)
                    {
                        $merged[$key][$route_id] = $route_definition;
                        if (isset($base[$key][$route_id]))
                        {
                            unset($base[$key][$route_id]);
                        }
                    }
                    
                    foreach ($base[$key] as $route_id => $route_definition)
                    {
                        $merged[$key][$route_id] = $route_definition;
                    }
                }
                $merged[$key] = self::merge_configs($merged[$key], $value);
                
                continue;
            }
            $merged[$key] = $value;
        }
        
        return $merged;
    }

    /**
     * Internal helper of loading configuration array from a file
     *
     * @param string $snippet_path
     * @return array
     */
    private function load_file($file_path)
    {
        if (!file_exists($file_path))
        {
            return array();
        }
        
        $yaml = file_get_contents($file_path);
        $configuration = $this->unserialize($yaml);
        if (!is_array($configuration))
        {
            return array();
        }
        return $configuration;
    }

    private function get_identifier()
    {
        return $this->mvc->context->get_context_identifier();
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
    public function get($key, $subkey = null)
    {
        if (!$this->exists($key))
        {
            $identifier = $this->get_identifier();
            throw new OutOfBoundsException("Configuration key '{$key}' does not exist in {$identifier}.");
        }

        $identifier = $this->get_identifier();
        if (!is_null($subkey))
        {
            if (!isset(self::$configuration[$identifier][$key][$subkey]))
            {
                throw new OutOfBoundsException("Configuration key '{$key}/{$subkey}' does not exist in {$identifier}.");
            }

            return self::$configuration[$identifier][$key][$subkey];
        }

        return self::$configuration[$identifier][$key];
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
        $identifier = $this->get_identifier();
        if (!isset(self::$configuration[$identifier]))
        {
            // Empty configuration, we need to merge it
            $this->prepare_stack($identifier);
        }

        return array_key_exists($key, self::$configuration[$identifier]);
    }

    public function __isset($key)
    {
        return $this->exists($key);
    }

    private function prepare_stack($identifier)
    {
        // Include Midgard MVC and current component
        self::$configuration[$identifier] = self::merge_configs(self::$configuration['midgardmvc_core'], self::$configuration[$this->mvc->context->component]);

        // Include injectors
        foreach (self::$injectors as $injector)
        {
            self::$configuration[$identifier] = self::merge_configs(self::$configuration[$identifier], self::$configuration[$injector]);
        }

        // TODO: Local configs from node
    }

    /**
     * Parses configuration string and returns it in configuration array format
     *
     * @param string $configuration Configuration string
     * @return array The loaded configuration array
     */
    public function unserialize($configuration)
    {
        if (!self::$use_yaml)
        {
            return Spyc::YAMLLoad($configuration);
        }

        return yaml_parse($configuration);
    }
    
    /**
     * Dumps configuration array and returns it as a string
     *
     * @param array $configuration Configuration array     
     * @return string Configuration in string format
     */
    public function serialize(array $configuration)
    {
        if (!self::$use_yaml)
        {
            return Spyc::YAMLDump($configuration);
        }

        return yaml_emit($configuration);
    }
    
    /**
     * Normalizes routes configuration to include needed data
     *
     * @param array $route routes configuration
     * @return array normalized routes configuration
     */
    public function normalize_routes(midgardmvc_core_helpers_request $request, array $routes = null)
    {
        if (is_null($routes))
        {
            if ($request->isset_data_item('component_routes'))
            {
                // We already have normalized routes for this context
                return $request->get_data_item('component_routes');
            }
            $routes = $this->get('routes');
        }

        $root_page = false;
        if ($request->get_node() == $request->get_root_node())
        {
            $root_page = true;
        }

        $normalized_routes = array();
        foreach ($routes as $identifier => $route)
        {
            if (   isset($route['root_only'])
                && $route['root_only']
                && !$root_page)
            {
                // Drop root-only routes from subpages
                continue;
            }
            
            // Handle the required route parameters
            if (!isset($route['controller']))
            {
                throw Exception("Route {$identifier} has no controller defined");
            }

            if (!isset($route['action']))
            {
                throw Exception("Route {$identifier} has no action defined");
            }

            if (!isset($route['route']))
            {
                throw Exception("Route {$identifier} has no route path defined");
            }

            // Normalize additional parameters
            if (!isset($route['allowed_methods']))
            {
                // Add default HTTP allowed methods
                $route['allowed_methods'] = array
                (
                    'OPTIONS',
                    'GET',
                    'POST',
                );
            }
            
            if (!isset($route['mimetype']))
            {
                $route['mimetype'] = 'text/html';
            }
            
            if (!isset($route['template_entry_point']))
            {
                // Add default HTTP allowed methods
                $route['template_entry_point'] = 'ROOT';
            }

            if (!isset($route['content_entry_point']))
            {
                // Add default HTTP allowed methods
                $route['content_entry_point'] = 'content';
            }

            $normalized_routes[$identifier] = $route;
        }
        return $normalized_routes;
    }
    /**
     * Normalizes given route definition ready for parsing
     *
     * @param string $route route definition
     * @return string normalized route
     */
    public function normalize_route_path($route)
    {
        // Normalize route
        if (   strpos($route, '?') === false
            && substr($route, -1, 1) !== '/')
        {
            $route .= '/';
        }
        return preg_replace('%/{2,}%', '/', $route);
    }

    /**
     * Splits a given route (after normalizing it) to it's path and get parts
     *
     * @param string $route reference to a route definition
     * @return array first item is path part, second is get part, both default to boolean false
     */
    public function split_route(&$route)
    {
        $route_path = false;
        $route_get = false;
        $route_args = false;
        
        /* This will split route from "@" - mark
         * /some/route/@somedata
         * $matches[1] = /some/route/
         * $matches[2] = somedata
         */
        preg_match('%([^@]*)@%', $route, $matches);
        
        if(count($matches) > 0)
        {
            $route_args = true;
        }
        
        $route = $this->normalize_route_path($route);
        // Get route parts
        $route_parts = explode('?', $route, 2);
        $route_path = $route_parts[0];
        if (isset($route_parts[1]))
        {
            $route_get = $route_parts[1];
        }
        unset($route_parts);
        return array($route_path, $route_get, $route_args);
    }
}
?>
