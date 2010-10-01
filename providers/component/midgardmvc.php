<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Midgard MVC filesystem component provider
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_providers_component_midgardmvc implements midgardmvc_core_providers_component
{
    public $components = array();
    private $injectors = array
    (
        'process' => array(),
        'template' => array(),
    );
    private $manifests = array();

    public function __construct()
    {
        $this->load_all_manifests();
    }

    public function get($component)
    {
        if (!is_string($component))
        {
            throw new InvalidArgumentException('Invalid component name given, expected string but got ' . gettype($component));
        }
        if (isset($this->components[$component]))
        {
            // Component is installed and already loaded
            return $this->components[$component];
        }
        if (!isset($this->manifests[$component]))
        {
            $manifest_path = $this->get_manifest_path($component);
            if (!file_exists($manifest_path))
            {
                throw new OutOfRangeException("Component {$component} is not installed");
            }
            $this->manifests[$component] = $this->load_manifest_file($manifest_path);
        }
        $this->components[$component] = new midgardmvc_core_providers_component_component_midgardmvc($component, $this->manifests[$component]);

        midgardmvc_core::get_instance()->i18n->set_translation_domain($component);

        return $this->components[$component];
    }

    public function is_installed($component)
    {
        if (isset($this->components[$component]))
        {
            // Component is installed and already loaded
            return true;
        }

        if (file_exists($this->get_manifest_path($component)))
        {
            // Component is installed
            return true;
        }

        return false;
    }

    public function get_routes(midgardmvc_core_request $request)
    {
        $routes = array();
        $components = array_reverse($request->get_component_chain());
        foreach ($components as $component)
        {
            $component_routes = $component->get_routes($request);
            foreach ($component_routes as $route_id => $route)
            {
                $routes[$route_id] = $route;
            }
        }
        return $routes;
    }

    public function inject(midgardmvc_core_request $request, $injector_type)
    {
        $inject_method = "inject_{$injector_type}";
        foreach ($this->injectors[$injector_type] as $key => $injector_class)
        {
            $injector = new $injector_class(); 
            $injector->$inject_method();
        }
    }

    private function get_manifest_path($component)
    {
        return MIDGARDMVC_ROOT . "/{$component}/manifest.yml";
    }

    /**
     * Load a component manifest file
     *
     * @param string $manifest_file Path of the manifest file
     */
    private function load_manifest_file($manifest_file)
    {
        static $loaded_manifests = array();
        if (isset($loaded_manifests[$manifest_file]))
        {
            $loaded_manifests[$manifest_file];
        }

        $manifest_yaml = file_get_contents($manifest_file);
        if (!extension_loaded('yaml'))
        {
            // YAML PHP extension is not loaded, include the pure-PHP implementation
            require_once MIDGARDMVC_ROOT. '/midgardmvc_core/helpers/spyc.php';
            $manifest = Spyc::YAMLLoad($manifest_yaml);
        }
        else
        {
            $manifest = yaml_parse($manifest_yaml);
        }

        // Normalize manifest
        if (!isset($manifest['version']))
        {
            $manifest['version'] = '0.0.1devel';
        }
        if (!isset($manifest['extends']))
        {
            $manifest['extends'] = null;
        }

        if (isset($manifest['process_injector']))
        {
            // This component has an injector for the process() phase
            $this->injectors['process'][$manifest['component']] = $manifest['process_injector'];
        }

        if (isset($manifest['template_injector']))
        {
            // This component has an injector for the template() phase
            $this->injectors['template'][$manifest['component']] = $manifest['template_injector'];
        }
        $loaded_manifests[$manifest_file] = $manifest;
        return $manifest;
    }

    private function load_all_manifests()
    {
        if (   !isset($_MIDGARD)
            || empty($_MIDGARD))
        {
            $cache_identifier = 'CLI';
        }
        else
        {
            $cache_identifier = "{$_MIDGARD['sitegroup']}-{$_MIDGARD['host']}";
        }

        // $manifests = midgardmvc_core::get_instance()->cache->get('manifest', $cache_identifier); // FIXME: Take account midgard configuration as it's possible
        $manifests = false;
        $_core = midgardmvc_core::get_instance();

        if (   !$manifests
            || !is_array($manifests))
        {
            // Load manifests and cache them
            $manifest_files = array();
            $MIDGARDMVC_ROOT = dir(MIDGARDMVC_ROOT);
            
            while (false !== ($component = $MIDGARDMVC_ROOT->read())) 
            {
                if (   substr($component, 0, 1) == '.'
                    || $component == 'scaffold'
                    || $component == 'PHPTAL'
                    || $component == 'PHPTAL.php')
                {
                    continue;
                }
                $component_path = MIDGARDMVC_ROOT . "/{$component}";
                if (!file_exists("{$component_path}/manifest.yml"))
                {
                    continue;
                }
                
                $this->manifests[$component] = $this->load_manifest_file("{$component_path}/manifest.yml");
            }
            $MIDGARDMVC_ROOT->close();
            
            /*
            exec('find ' . escapeshellarg(MIDGARDMVC_ROOT) . ' -follow -type f -name ' . escapeshellarg('manifest.yml'), $manifest_files);
            foreach ($manifest_files as $manifest)
            {
                if (strpos($manifest, 'scaffold') !== false)
                {
                    continue;
                }
                $this->load_manifest_file($manifest);
            }
            */
            
            //$_core->cache->put('manifest', $cache_identifier, $this->manifests);
            return;
        }

        foreach ($manifests as $manifest)
        {
            $this->load_manifest($manifest);
        }
    }
}
