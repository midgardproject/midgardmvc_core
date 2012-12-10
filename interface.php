<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Midgard MVC interface class
 *
 * @package midgardmvc_core
 */
class midgardmvc_core
{
    /**
     * @var midgardmvc_core_services_configuration_yaml
     */
    public $configuration;

    /**
     * @var midgardmvc_core_helpers_context
     */
    public $context;
    
    private static $instance = null;

    public function __construct()
    {
    }
    
    /**
     * Load all basic services needed for Midgard MVC usage. This includes configuration, authorization and the component loader.
     */
    public function load_base_services(array $local_configuration = null)
    {
        // Load the context helper and initialize first context
        $this->context = new midgardmvc_core_helpers_context();

        $this->configuration = new midgardmvc_core_services_configuration_chain($local_configuration);

        if (!$this->configuration->development_mode)
        {
            // Disable assertions
            assert_options(ASSERT_ACTIVE, false);
            return;
        }

        assert_options(ASSERT_ACTIVE, true);
        assert_options(ASSERT_WARNING, true);
        assert_options(ASSERT_CALLBACK, 'midgardmvc_core_exceptionhandler::handle_assert');
    }
    
    /**
     * Helper for service initialization. Usually called via getters
     *
     * @param string $service Name of service to load
     */
    private function load_service($service)
    {
        $interface_file = self::get_component_path('midgardmvc_core') . "/services/{$service}.php";
        if (!file_exists($interface_file))
        {
            throw new InvalidArgumentException("Service {$service} not installed");
        }
        
        if (!$this->configuration->exists("services_{$service}"))
        {
            throw new Exception("No implementation defined for service {$service}");
        }

        $service_implementation = $this->configuration->get("services_{$service}");
        if (strpos($service_implementation, '_') === false)
        {
            // Built-in service implementation called using the shorthand notation
            $service_implementation = "midgardmvc_core_services_{$service}_{$service_implementation}";
        }

        $this->$service = new $service_implementation();
    }

    /**
     * Helper for service initialization. Usually called via getters
     *
     * @param string $service Name of service to load
     */
    private function load_provider($provider)
    {
        $interface_file = self::get_component_path('midgardmvc_core') . "/providers/{$provider}.php";
        if (!file_exists($interface_file))
        {
            throw new InvalidArgumentException("Provider {$provider} not installed");
        }
        
        if (!$this->configuration->exists("providers_{$provider}"))
        {
            throw new Exception("No implementation defined for provider {$provider}");
        }

        $provider_implementation = $this->configuration->get("providers_{$provider}");
        if (strpos($provider_implementation, '_') === false)
        {
            // Built-in provider implementation called using the shorthand notation
            $provider_implementation = "midgardmvc_core_providers_{$provider}_{$provider_implementation}";
        }

        $this->$provider = new $provider_implementation();
    }

    /**
     * Logging interface
     *
     * @param string $prefix Prefix to file the log under
     * @param string $message Message to be logged
     * @param string $loglevel Logging level, may be one of debug, info, message and warning
     */
    public function log($prefix, $message, $loglevel = 'debug')
    {
        if (!extension_loaded('midgard2'))
        {
            $this->log_with_helper($prefix, $message, $loglevel);
            return;
        }

        midgard_error::$loglevel("{$prefix}: {$message}");
    }

    private function log_with_helper($prefix, $message, $loglevel)
    {
        // Temporary non-Midgard logger until midgard_error is backported to Ragnaroek
        static $logger = null;
        if (!$logger)
        {
            try
            {
                $logger = new midgardmvc_core_helpers_log();
            }
            catch (Exception $e)
            {
                // Unable to instantiate logger
                return;
            }
        }
        static $log_levels = array
        (
            'debug' => 4,
            'info' => 3,
            'message' => 2,
            'warn' => 1,
        );
        
        if ($log_levels[$loglevel] > $log_levels[$this->configuration->get('log_level')])
        {
            // Skip logging, too low level
            return;
        }
        $logger->log("{$prefix}: {$message}");
    }
    
    /**
     * Magic getter for service and provider loading
     */
    public function __get($key)
    {
        try
        {
            // First try loading as a service
            $this->load_service($key);
        }
        catch (InvalidArgumentException $e)
        {
            // Load a provider instead
            $this->load_provider($key);
        }
        return $this->$key;
    }
    
    private static function find_components()
    {
        static $components = null;
        if ($components)
        {
            return $components;
        }

        if (strpos(__DIR__, 'vendor/midgard') !== false)
        {
            // Composer-based installation has different structure
            $components = self::find_components_composer();
            return $components;
        }

        $component_dirs = scandir(MIDGARDMVC_ROOT, -1);
        $components = array();
        foreach ($component_dirs as $component)
        {
            $components[$component] = MIDGARDMVC_ROOT . "/{$component}";
        }
        return $components;
    }

    private static function find_components_composer()
    {
        $components = array();
        $project_root = realpath(__DIR__ . '/../../..');
        if (file_exists("{$project_root}/manifest.yml")) {
            // Main Composer package is also a component
            $component_yaml = file_get_contents("{$project_root}/manifest.yml");
            $component_config = midgardmvc_core::read_yaml($component_yaml);
            if (isset($component_config['name']))
            {
                $component_name = $component_config['name'];
            }
            else
            {
                $component_name = basename($project_root);
            }
            $components[$component_name] = $project_root;
        }

        // Find package namespaces
        $composer_root = dirname(MIDGARDMVC_ROOT);
        $package_namespaces = scandir($composer_root);
        foreach ($package_namespaces as $namespace) {
            if (substr($namespace, 0, 1) === '.') {
                continue;
            }
            // Find components inside namespace
            $namespace_root = "{$composer_root}/{$namespace}";
            if (!is_dir($namespace_root)) {
                continue;
            }
            $component_dirs = scandir($namespace_root, -1);
            foreach ($component_dirs as $component)
            {
                if (substr($namespace, 0, 1) === '.') {
                    continue;
                }
                if (!file_exists("{$namespace_root}/{$component}/manifest.yml")) {
                    continue;
                }
                $component_name = str_replace('-', '_', $component);
                $components[$component_name] = "{$namespace_root}/{$component}";
            }
        }
        return $components;
    }

    public static function get_component_path($component)
    {
        $components = self::find_components();
        if (isset($components[$component])) {
            return $components[$component];
        }
    }
    
    /**
     * Process the current request, loading the node's component and dispatching the request to it
     */
    public function process()
    {
        try
        {
            $request = $this->_process();
        }
        catch (Exception $e)
        {
            // ->serve() wouldn't be called — do cleanup here
            $this->_after_process();
            $this->cleanup_after_request();

            // rethrowing exception, if there is one
            throw $e;
        }

        $this->_after_process();
        return $request;
    }

    private function _process()
    {
        // Let dispatcher populate request with the node and other information used
        $request = $this->dispatcher->get_request();
        $request->add_component_to_chain($this->component->get('midgardmvc_core'));

        // TODO: We give it to context to emulate legacy functionality
        $this->context->create($request);

        // Load the head helper
        $this->head = new midgardmvc_core_helpers_head();

        // Check authentication
        $this->authentication->check_session();

        // Disable cache for now
        $request->set_data_item('cache_enabled', false);

        $this->log('Midgard MVC', 'Serving ' . $request->get_method() . ' ' . $request->get_path() . ' at ' . gmdate('r'), 'info');

        // Let injectors do their work
        $this->component->inject($request, 'process');

        try
        {
            $this->dispatcher->dispatch($request);
        }
        catch (midgardmvc_exception_unauthorized $exception)
        {
            // Pass the exception to authentication handler
            $this->authentication->handle_exception($exception);
        }

        $this->dispatcher->header('Content-Type: ' . $request->get_data_item('mimetype'));

        return $request;
    }

    private function _after_process()
    {
        // add any cleanup after process() here
    }

    /**
     * Serve a request either through templating or the WebDAV server
     */
    public function serve(midgardmvc_core_request $request)
    {
        try
        {
            $this->_serve($request);
        }
        catch (Exception $e)
        {
            // this will be executed even if _serve() had exception
            $this->_after_serve();
            throw $e;
        }

        $this->_after_serve();
    }

    private function _serve(midgardmvc_core_request $request)
    {
        // Prepate the templates
        $this->templating->template($request);

        // Read contents from the output buffer and pass to Midgard MVC rendering
        $this->templating->display($request);
    }

    private function _after_serve()
    {
        // add any cleanup after serve() here
        $this->cleanup_after_request();
    }

    private function cleanup_after_request()
    {
        // commit session
        if ($this->dispatcher->session_is_started())
        {
            $this->dispatcher->session_commit();
        }

        // Clean up the context
        $this->context->delete_all();
    }

    /**
     * Access to the Midgard MVC instance
     */
    public static function get_instance($local_configuration = null)
    {
        if (!is_null(self::$instance))
        {
            return self::$instance;
        }

        $configuration = self::validate_configuration
        (
            self::read_configuration($local_configuration)
        );

        // Load and return MVC instance
        self::$instance = new midgardmvc_core();
        self::$instance->load_base_services($configuration);
        return self::$instance;
    }

    public static function clear_instance()
    {
        self::$instance = null;
    }

    private static function read_configuration($local_configuration = null)
    {
        if (is_array($local_configuration))
        {
            // Configuration passed as a PHP array
            return $local_configuration;
        }
        elseif (   is_string($local_configuration)
                && substr($local_configuration, 0, 1) == '/')
        {
            // Application YAML file provided, load configuration from it
            if (!file_exists($local_configuration))
            {
                throw new Exception("Application configuration file {$local_configuration} not found");
            }
            $configuration_yaml = file_get_contents($local_configuration);
            return midgardmvc_core::read_yaml($configuration_yaml);
        }

        throw new Exception('Unrecognized configuration given for Midgard MVC initialization');
    }
    
    private static function validate_configuration(array $configuration)
    {
        if (!isset($configuration['services_dispatcher']))
        {
            throw new Exception('Dispatcher not defined in configuration given for Midgard MVC initialization');
        }

        if (!isset($configuration['providers_component']))
        {
            throw new Exception('Component provider not defined in configuration given for Midgard MVC initialization');
        }
        
        return $configuration;
    }

    public static function read_yaml($yaml_string)
    {
        if (empty($yaml_string))
        {
            return array();
        }
        static $use_yaml = null;
        static $yaml_function = null;
        if (is_null($use_yaml))
        {
            // Check for YAML extension
            if (extension_loaded('yaml'))
            {
                $yaml_function = 'yaml_parse';
                $use_yaml = true;
            }
            elseif (extension_loaded('syck'))
            {
                $yaml_function = 'syck_load';
                $use_yaml = true;
            }
        }

        if (!$use_yaml)
        {
            return Symfony\Component\Yaml\Yaml::parse($yaml_string);
        }
        return $yaml_function($yaml_string);
    }

    public static function write_yaml($yaml)
    {
        if (empty($yaml))
        {
            return '';
        }

        static $use_yaml = null;
        static $yaml_function = null;
        if (is_null($use_yaml))
        {
            // Check for YAML extension
            if (extension_loaded('yaml'))
            {
                $yaml_function = 'yaml_emit';
                $use_yaml = true;
            }
            elseif (extension_loaded('syck'))
            {
                $yaml_function = 'syck_dump';
                $use_yaml = true;
            }

            if (!$use_yaml)
            {
                // YAML PHP extension is not loaded, include the pure-PHP implementation
                if (!class_exists('sfYaml'))
                {
                    require midgardmvc_core::get_component_path('midgardmvc_core') . '/helpers/sfYaml/sfYaml.php';
                }
            }
        }

        if (!$use_yaml)
        {
            return sfYaml::dump($yaml);
        }
        return $yaml_function($yaml);
    }
}
?>
