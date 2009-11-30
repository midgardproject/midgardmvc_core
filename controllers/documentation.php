<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Midgard MVC documentation display controller
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_controllers_documentation
{
    public function __construct(midgardmvc_core_component_interface $instance)
    {
        $this->midgardmvc = midgardmvc_core::get_instance();
        $this->configuration = $this->midgardmvc->configuration;
    }
    
    private function prepare_component($component)
    {
        $this->data['component'] = $component;
        
        if (   $this->data['component'] != 'midgardmvc_core'
            && !$this->midgardmvc->componentloader->load($this->data['component']))
        {
            throw new midgardmvc_exception_notfound("Component {$this->data['component']} not found");
        }
    }

    private function list_directory($path, $prefix = '')
    {
        $files = array
        (
            'name'    => basename($path),
            'label'   => ucfirst(str_replace('_', ' ', basename($path))),
            'folders' => array(),
            'files'   => array(),
        );

        if (!file_exists($path))
        {
            return $files;
        }

        $directory = dir($path);
        while (false !== ($entry = $directory->read()))
        {
            if (substr($entry, 0, 1) == '.')
            {
                // Ignore dotfiles
                continue;
            }

            if (is_dir("{$path}/{$entry}"))
            {
                // List subdirectory
                $files['folders'][$entry] = $this->list_directory("{$path}/{$entry}", "{$prefix}{$entry}/");
                continue;
            }
            
            $pathinfo = pathinfo("{$path}/{$entry}");
            
            if (   !isset($pathinfo['extension'])
                || $pathinfo['extension'] != 'markdown')
            {
                // We're only interested in Markdown files
                continue;
            }
            
            $files['files'][] = array
            (
                'label' => ucfirst(str_replace('_', ' ', $pathinfo['filename'])),
                'path' => "{$prefix}{$pathinfo['filename']}/",
            );
        }
        $directory->close();
        return $files;
    }

    public function get_index(array $args)
    {
        $this->midgardmvc->authorization->require_user();
        $this->prepare_component($args['component'], $this->data);

        $this->data['files'] = $this->list_directory(MIDGARDMVC_ROOT . "/{$this->data['component']}/documentation");

        $configuration = new midgardmvc_core_services_configuration_yaml($this->data['component']);
        $this->data['routes'] = $configuration->get('routes');
        if ($this->data['routes'])
        {
            $this->data['files']['files'][] = array
            (
                'label' => 'Routes',
                'path' => 'routes/',
            );
        }
    }

    public function get_show(array $args)
    {
        $this->midgardmvc->authorization->require_user();
        $this->prepare_component($args['variable_arguments'][0], $this->data);
        $path = MIDGARDMVC_ROOT . "/{$this->data['component']}/documentation";
        foreach ($args['variable_arguments'] as $key => $argument)
        {
            if ($key == 0)
            {
                continue;
            }
            
            if ($argument == '..')
            {
                continue;
            }
            
            $path .= "/{$argument}";
        }

        if (   file_exists($path)
            && !is_dir($path))
        {
            // Image or other non-Markdown doc file, pass directly
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            $mimetype = 'application/octet-stream';
            switch ($extension)
            {
                case 'png':
                    $mimetype = 'image/png';
                    break;
            }
            header("Content-type: {$mimetype}");
            readfile($path);
            die();
        }

        $path .= '.markdown';
        if (!file_exists($path))
        {
            throw new midgardmvc_exception_notfound("File not found");
        }

        require_once 'markdown.php';        
        $this->data['markdown'] = file_get_contents($path);
        $this->data['markdown_formatted'] = Markdown($this->data['markdown']);
    }
    
    public function get_routes(array $args)
    {
        $this->midgardmvc->authorization->require_user();
        $this->prepare_component($args['component'], $this->data);

        $configuration = new midgardmvc_core_services_configuration_yaml($this->data['component']);
        $this->data['routes'] = $configuration->get('routes');
        
        if (!$this->data['routes'])
        {
            throw new midgardmvc_exception_notfound("Component {$this->data['component']} has no routes");
        }
        
        foreach ($this->data['routes'] as $route_id => $route_def)
        {
            // Some normalization
            $this->data['routes'][$route_id]['id'] = $route_id;
            
            if (!isset($route_def['template_entry_point']))
            {
                $this->data['routes'][$route_id]['template_entry_point'] = 'ROOT';
            }

            if (!isset($route_def['content_entry_point']))
            {
                $this->data['routes'][$route_id]['content_entry_point'] = 'content';
            }
            
            $this->data['routes'][$route_id]['controller_action'] = "{$route_def['controller']}:{$route_def['action']}";
            
            $this->data['routes'][$route_id]['controller_url'] = $this->midgardmvc->dispatcher->generate_url('midcom_documentation_class', array('class' => $route_def['controller']));
            $this->data['routes'][$route_id]['controller_url'] .= "#action_{$route_def['action']}";
        }
    }

    public function get_class(array $args)
    {
        $this->midgardmvc->authorization->require_user();

        $this->data['class'] = $args['class'];
        if (!class_exists($this->data['class']))
        {
            throw new midgardmvc_exception_notfound("Class {$this->data['class']} not defined");
        }
        
        $reflectionclass = new midgard_reflection_class($this->data['class']);
        $this->data['class_documentation'] = midgardmvc_core_helpers_documentation::get_class_documentation($reflectionclass);

        $this->data['properties'] = midgardmvc_core_helpers_documentation::get_property_documentation($this->data['class']);
        $this->data['signals'] = midgardmvc_core_helpers_documentation::get_signal_documentation($this->data['class']);
 
        $this->data['methods'] = array();
        $this->data['abstract_methods'] = array(); 
        $this->data['static_methods'] = array(); 
        $reflectionmethods = $reflectionclass->getMethods();
        foreach ($reflectionmethods as $method)
        {
            $method_docs = midgardmvc_core_helpers_documentation::get_method_documentation($this->data['class'], $method->getName());
            if (isset($method_docs['abstract']))
            {
                $this->data['abstract_methods'][] = $method_docs;
                continue;
            }
            elseif (isset($method_docs['static']))
            {
                $this->data['static_methods'][] = $method_docs;
                continue;
            }
            $this->data['methods'][] = $method_docs;
        }
    }
}
?>
