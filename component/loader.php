<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Midgard MVC component loader
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_component_loader
{
    public $manifests = array();
    public $authors = array();
    private $tried_to_load = array();
    private $interfaces = array();
    private $process_injectors = array();
    private $template_injectors = array();
    private $type_action_providers = array();
    private $category_action_providers = array();
    private $_core;

    public function __construct()
    {
        $this->_core = midgardmvc_core::get_instance();
        $this->load_all_manifests();
    }
    
    public function can_load($component)
    {
        if (isset($this->tried_to_load[$component]))
        {
            // We have already loaded (or tried and failed to load) the component
            return $this->tried_to_load[$component];
        }
            
        if (!isset($this->manifests[$component]))
        {
            return false;
        }
        
        if (preg_match('/^[a-z][a-z0-9\_]*[a-z0-9]$/', $component) !== 1)
        {        
            return false;
        }
        
        return true;
    }
    
    public function load($component, midgard_page $folder = null)
    {
        if (! $this->can_load($component))
        {
            $this->tried_to_load[$component] = false;
            return false;
        }
        
        if (   isset($this->interfaces[$component])
            && $this->tried_to_load[$component])
        {
            // We have already loaded the component
            return $this->interfaces[$component];
        }
        
        $component_directory = $this->component_to_filepath($component);
        if (!is_dir($component_directory))
        {        
            // No component directory
            $this->tried_to_load[$component] = false;

            throw new OutOfRangeException("Component {$component} directory not found.");
        }  
        $component_interface_file = "{$component_directory}/interface.php";
        if (! file_exists($component_interface_file))
        {
            // No interface class
            // TODO: Should we default to some baseclass?
            $this->tried_to_load[$component] = false;
            
            throw new OutOfRangeException("Component {$component} interface class file not found.");
        }
        
        if (! class_exists($component))
        {
            require($component_interface_file);
        }

        // Load configuration for the component
        $configuration =& midgardmvc_core::get_instance()->configuration;
        $configuration->load_component_configuration($component);

        // Load the interface class
        $this->interfaces[$component] = new $component($configuration, $folder);
        
        if ($this->_core->head->jsmidgardmvc_enabled)
        {
            $js_component_file = "{$component_directory}/static/component.js";
            if (file_exists($js_component_file))
            {
                $this->_core->head->add_jsfile(MIDGARDMVC_STATIC_URL . "/{$component}/component.js");
            }
        }
        
        $this->tried_to_load[$component] = true;
        return $this->interfaces[$component];
    }

    /**
     * Get the component that is parent of current component
     */
    public function get_parent($component)
    {
        return $this->manifests[$component]['extends'];
    }

    /**
     * Helper, converting a component name (net_nehmer_blog)
     * to a file path (/net/nehmer/blog).
     *
     * @param string $component Component name
     * @return string File path
     */
    public function component_to_filepath($component)
    {
        if (!isset($this->manifests[$component]))
        {
            throw new OutOfRangeException("Component {$component} not installed.");
        }
        return MIDGARDMVC_ROOT . '/' . $component;// . strtr($component, '_', '/');
    }

    /**
     * Load a component manifest file
     *
     * @param string $manifest_file Path of the manifest file
     */
    private function load_manifest_file($manifest_file)
    {
        if (! file_exists($manifest_file))
        {
            return false;
        }
        
        $manifest_yaml = file_get_contents($manifest_file);

        if (!extension_loaded('syck'))
        {
            // Syck PHP extension is not loaded, include the pure-PHP implementation
            require_once('midgardmvc_core/helpers/spyc.php');
            $manifest = Spyc::YAMLLoad($manifest_yaml);
        }
        else
        {
            $manifest = syck_load($manifest_yaml);
        }

        // Normalize manifest
        if (!isset($manifest['version']))
        {
            $manifest['version'] = '0.0.1devel';
        }
        if (!isset($manifest['authors']))
        {
            $manifest['authors'] = array();
        }
        if (!isset($manifest['extends']))
        {
            $manifest['extends'] = null;
        }
        foreach ($manifest['authors'] as $username => $author)
        {
            if (!isset($author['name']))
            {
                $manifest['authors'][$username]['name'] = '';
            }
            
            if (!isset($author['url']))
            {
                $manifest['authors'][$username]['url'] = 'http://www.midgard-project.org';
            }
        }

        $this->load_manifest($manifest);
    }

    /**
     * Load component manifest data
     *
     * @param array $manifest Component manifest
     */
    private function load_manifest(array $manifest)
    {
        foreach ($manifest['authors'] as $username => $author)
        {
            if (!isset($this->authors[$username]))
            {
                $this->authors[$username] = $manifest['authors'][$username];
            }
        }
        
        if (!isset($this->manifests[$manifest['component']]))
        {
            $this->manifests[$manifest['component']] = $manifest;
        }
        
        if (isset($manifest['process_injector']))
        {
            // This component has an injector for the process() phase
            $this->process_injectors[$manifest['component']] = $manifest['process_injector'];
        }

        if (isset($manifest['template_injector']))
        {
            // This component has an injector for the template() phase
            $this->template_injectors[$manifest['component']] = $manifest['template_injector'];
        }

        if (isset($manifest['action_types']))
        {
            // This component provides actions for some Midgard types
            foreach ($manifest['action_types'] as $type)
            {
                if (!isset($this->type_action_providers[$type]))
                {
                    $this->type_action_providers[$type] = array();
                }
                $this->type_action_providers[$type][] = $manifest['component'];
            }
        }

        if (isset($manifest['action_categories']))
        {
            // This component provides actions for some toolbar categories
            foreach ($manifest['action_categories'] as $category)
            {
                if (!isset($this->category_action_providers[$category]))
                {
                    $this->category_action_providers[$category] = array();
                }
                $this->category_action_providers[$category][] = $manifest['component'];
            }
        }
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

        $manifests = midgardmvc_core::get_instance()->cache->get('manifest', $cache_identifier); // FIXME: Take account midgard configuration as it's possible

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
                    || $component == 'tests'
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
                
                $this->load_manifest_file("{$component_path}/manifest.yml");
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
            
            $_core->cache->put('manifest', $cache_identifier, $this->manifests);
            return;
        }

        foreach ($manifests as $manifest)
        {
            $this->load_manifest($manifest);
        }
    }

    /**
     * Injectors are component classes that manipulate the context
     */
    private function inject($injector_type)
    {
        $injector_array = "{$injector_type}_injectors";
        $injector_method = "inject_{$injector_type}";
        foreach ($this->$injector_array as $component => $injector_class)
        {
            // Ensure the component is loaded
            $this->load($component);

            // Instantiate the injector class
            $injector = new $injector_class($this->_core->configuration);
            
            // Inject
            $injector->$injector_method();
        }
    }

    public function inject_process()
    {
        $this->inject('process');
    }

    public function inject_template()
    {
        $this->inject('template');
    }

    public function get_action_categories()
    {
        return array_keys($this->category_action_providers);
    }

    public function get_object_actions($object)
    {
        $actions = array();

        if (!is_object($object))
        {
            return $actions;
        }
        $type = get_class($object);
        
        $components_to_check = array();
        $libraries_to_check = array();

        if (isset($this->type_action_providers[$type]))
        {
            // Type-specific actions
            foreach ($this->type_action_providers[$type] as $component)
            {
                if (   isset($this->manifests[$component]['library'])
                    && $this->manifests[$component]['library'])
                {
                    $libraries_to_check[] = $component;
                    continue;
                }
                $components_to_check[] = $component;
            }
        }

        if (isset($this->type_action_providers['*']))
        {
            // Generic actions for any type
            foreach ($this->type_action_providers['*'] as $component)
            {
                if (   isset($this->manifests[$component]['library'])
                    && $this->manifests[$component]['library'])
                {
                    $libraries_to_check[] = $component;
                    continue;
                }
                $components_to_check[] = $component;
            }
        }

        foreach ($libraries_to_check as $component)
        {
            $interface = $this->_core->componentloader->load($component);
            $component_actions = $interface->get_object_actions($object);
            $actions = array_merge($actions, $component_actions);
        }

        if (!empty($components_to_check))
        {
            $qb = new midgard_query_builder('midgard_page');
            $qb->add_constraint('component', 'IN', $components_to_check);
            $folders = $qb->execute();
            foreach ($folders as $folder)
            {
                $interface = $this->_core->componentloader->load($folder->component, $folder);
                $component_actions = $interface->get_object_actions($object);
                $actions = array_merge($actions, $component_actions);
            }
        }

        return $actions;
    }

    public function get_category_actions($category, midgard_page $folder)
    {
        $actions = array();
        
        if (!isset($this->category_action_providers[$category]))
        {
            return $actions;
        }
        
        foreach ($this->category_action_providers[$category] as $component)
        {
            $interface = $this->_core->componentloader->load($component, $folder);
            $method = "get_{$category}_actions";
            $component_actions = $interface->$method($folder);
            $actions = array_merge($actions, $component_actions);
        }
        
        return $actions;
    }
}
?>
