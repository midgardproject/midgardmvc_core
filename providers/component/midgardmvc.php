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
    private $components_enabled = array();
    private $injectors = array
    (
        'process' => array(),
        'template' => array(),
    );
    private $manifests = array();

    public function __construct()
    {
        $this->components_enabled = midgardmvc_core::get_instance()->configuration->components;
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

        if (!isset($this->components_enabled[$component]))
        {
            throw new OutOfRangeException("Component {$component} is not enabled in application configuration");
        }

        if (!isset($this->manifests[$component]))
        {
            $manifest_path = $this->get_manifest_path($component);
            $this->manifests[$component] = $this->load_manifest_file($manifest_path);
        }
        $this->components[$component] = new midgardmvc_core_providers_component_component_midgardmvc($component, $this->manifests[$component]);

        midgardmvc_core::get_instance()->i18n->set_translation_domain($component);

        return $this->components[$component];
    }

    public function is_installed($component)
    {
        if (isset($this->components_enabled[$component]))
        {
            // Component is enabled
            return true;
        }

        return false;
    }

    public function get_components()
    {
        $components = array();
        foreach ($this->components_enabled as $component => $data)
        {
            $components[] = $this->get($component);
        }
        return $components;
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
            $injector->$inject_method($request);
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
        if (!file_exists($manifest_file))
        {
            throw new OutOfRangeException("Component manifest {$manifest_file} is not installed");
        }
        $manifest_yaml = file_get_contents($manifest_file);
        $manifest = midgardmvc_core::read_yaml($manifest_yaml);

        // Normalize manifest
        if (!isset($manifest['version']))
        {
            $manifest['version'] = '0.0.1devel';
        }

        if (!isset($manifest['extends']))
        {
            $manifest['extends'] = null;
        }
        elseif (!isset($this->components_enabled[$manifest['extends']]))
        {
            // Ensure the parent component is always enabled
            $this->components_enabled[$manifest['extends']] = array();
        }

        if (!isset($manifest['requires']))
        {
            $manifest['requires'] = null;
        }
        else
        {
            if (!is_array($manifest['requires']))
            {
                $manifest['requires'] = null;
            }

            // Ensure the required components are always enabled
            foreach ($manifest['requires'] as $component => $component_info)
            {
                $this->components_enabled[$component] = array();
            }
        }

        if (isset($manifest['process_injector']))
        {
            // This component has an injector for the process() phase
            $this->injectors['process'][$component] = $manifest['process_injector'];
        }

        if (isset($manifest['template_injector']))
        {
            // This component has an injector for the template() phase
            $this->injectors['template'][$component] = $manifest['template_injector'];
        }
        return $manifest;
    }

    private function load_all_manifests()
    {
        // Load manifests enabled in site configuration and cache them
        $components = midgardmvc_core::get_instance()->configuration->components;
        foreach ($components as $component => $setup_info)
        {
            $component_path = MIDGARDMVC_ROOT . "/{$component}";
            if (!file_exists("{$component_path}/manifest.yml"))
            {
                throw new Exception("Component {$component} specified in application configuration is not installed");
            }
            
            $this->manifests[$component] = $this->load_manifest_file("{$component_path}/manifest.yml");
        }
    }
}
