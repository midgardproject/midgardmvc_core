<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * MidCOM Style and Element WebDAV management controller
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_controllers_styles
{
    private $style = null;
    private $element = null;
    private $object_path = '';
    
    public function __construct($instance)
    {
        $this->configuration = midgardmvc_core::get_instance()->configuration;
    }
    
    private function get_style_children($style_id)
    {
        // Load children for PROPFIND purposes
        $children = array
        (
            array
            (
                'uri'      => "{midgardmvc_core::get_instance()->context->prefix}mgd:styles{$this->object_path}/", // FIXME: dispatcher::generate_url
                'title'    => $this->object_path,
                'mimetype' => 'httpd/unix-directory',
                'resource' => 'collection',
            )
        );
        
        // Styles
        $mc = midgard_style::new_collector('up', $style_id);
        $mc->set_key_property('name');
        $mc->execute(); 
        $styles = $mc->list_keys();
        if (is_array($styles))
        {
            foreach ($styles as $name => $array)
            {
                if (empty($name))
                {
                    continue;
                }
                $children[] = array
                (
                    'uri'      => "{midgardmvc_core::get_instance()->context->prefix}mgd:styles{$this->object_path}/{$name}/", // FIXME: dispatcher::generate_url
                    'title'    => $name,
                    'mimetype' => 'httpd/unix-directory',
                    'resource' => 'collection',
                );
            }
        }
        
        // Elements
        $qb = new midgard_query_builder('midgard_element');
        $qb->add_constraint('style', '=', $style_id);
        $qb->add_constraint('name', '<>', '');
        $elements = $qb->execute();
        if (is_array($elements))
        {
            foreach ($elements as $element)
            {
                $children[] = array
                (
                    'uri'      => "{midgardmvc_core::get_instance()->context->prefix}mgd:styles{$this->object_path}/{$element->name}.php", // FIXME: dispatcher::generate_url
                    'title'    => $element->name,
                    'mimetype' => 'text/plain',
                    'size'     => $element->metadata->size,
                    'revised'  => $element->metadata->revised,
                );
            }
        }
        
        return $children;
    }
    
    private function get_style($object_path)
    {
        try
        {
            $style = new midgard_style();
            $style->get_by_path($object_path);
        }
        catch (midgard_error_exception $e)
        {
            return false;
        }
        $this->style = $style;
        return true;
    }
    
    private function get_element($object_path)
    {
        try
        {
            $element = new midgard_element();
            $element->get_by_path(str_replace('.php', '', $object_path));
        }
        catch (midgard_error_exception $e)
        {
            return false;
        }
        
        $this->element = $element;
        return true;
    }
    
    private function handle_propfind($route_id, &$data)
    {
        if ($route_id == 'styles_root')
        {
            $data['children'] = $this->get_style_children(0);
            return;
        }
        
        if (!$this->get_style($this->object_path))
        {
            if (!$this->get_element($this->object_path))
            {
                throw new midgardmvc_exception_notfound("Style {$this->object_path} not found");
            }
            
            // Just put the element itself there
            $data['children'] = array
            (
                array
                (
                    'uri'      => "{midgardmvc_core::get_instance()->context->prefix}mgd:styles{$this->object_path}", // FIXME: dispatcher::generate_url
                    'title'    => $this->element->name,
                    'mimetype' => 'text/plain',
                    'size'     => $this->element->metadata->size,
                    'revised'  => $this->element->metadata->revised,
                )
            );
            return;
        }
        
        $data['children'] = $this->get_style_children($this->style->id);
    }

    private function handle_put($route_id, &$data)
    {
        if (   $route_id == 'styles_root'
            || $this->get_style($this->object_path))
        {
            throw new midgardmvc_exception_httperror("PUT to style not allowed", 405);
        }
        
        if (!$this->get_element($this->object_path))
        {
            $parent_path = dirname($this->object_path);
            if (!$this->get_style($parent_path))
            {
                throw new midgardmvc_exception_notfound("Style {$parent_path} not found");
            }
            midgardmvc_core::get_instance()->authorization->require_do('midgard:create', $this->style);
            
            $new_element = new midgard_element();
            $new_element->style = $this->style->id;
            $new_element->name = basename(str_replace('.php', '', $this->object_path));
            $new_element->create();
        }
        else
        {
            $new_element = $this->element;
        }
        
        $new_element->value = file_get_contents('php://input');
        $new_element->update();
    }

    private function handle_mkcol($route_id, &$data)
    {
        if (   $this->get_element
            || $this->style)
        {
            throw new midgardmvc_exception_httperror("MKCOL not allowed", 405);
        }
        
        $parent_path = dirname($this->object_path);
        if (   $parent_path != '/'
            && !$this->get_style($parent_path))
        {
            throw new midgardmvc_exception_notfound("Style {$parent_path} not found");
        }

        $new_style = new midgard_style();
        $new_style->name = basename($this->object_path);
        
        if ($parent_path != '/')
        {
            midgardmvc_core::get_instance()->authorization->require_do('midgard:create', $this->style);
            $new_style->up = $this->style->id;
        }
        
        $new_style->create();
    }

    private function check_destination($dest)
    {
        if (!isset($dest))
        {
            throw new Exception("No destination defined");
        }

        $elements_prefix = 'mgd:styles';
        if (substr($dest, 0, strlen($elements_prefix)) != $elements_prefix)
        {
            throw new Exception("Invalid destination {$dest}");
        }
        
        $destination_object_path = dirname(substr($dest, strlen($elements_prefix)));
        if (!$this->get_style($destination_object_path))
        {
            throw new Exception("No style {$destination_object_path} found");
        }
        
        $destination['style'] = $this->style;
        $destination['name'] = basename($dest);
        
        return $destination;
    }

    private function handle_copy($route_id, &$data)
    {
        $destination = $this->check_destination($data['dest']);
        
        if (!$this->get_style($this->object_path))
        {
            // Possibly copying elements instead
            if (!$this->get_element($this->object_path))
            {
                throw new midgardmvc_exception_notfound("Style {$this->object_path} not found");
            }

            $new_element = new midgard_element();
            $new_element->style = $destination['style']->id;
            $new_element->name = str_replace('.php', '', $destination['name']);
            $new_element->value = $this->element->value;
            $new_element->create();
            return;
        }

        $new_style = new midgard_style();
        $new_style->up = $destination['style']->id;
        $new_style->name = $destination['name'];
        $new_style->create();
    }

    private function handle_move($route_id, &$data)
    {
        $destination = $this->check_destination($data['dest']);
        
        if (!$this->get_style($this->object_path))
        {
            // Possibly moving elements instead
            if (!$this->get_element($this->object_path))
            {
                throw new midgardmvc_exception_notfound("Style {$this->object_path} not found");
            }

            $this->element->style = $destination['style']->id;
            $this->element->name = str_replace('.php', '', $destination['name']);
            $this->element->update();
            return;
        }

        $this->style->up = $destination['style']->id;
        $this->style->name = $destination['name'];
        $this->style->update();
    }

    public function get_object_webdav($route_id, &$data, $args)
    {
        if ($route_id == 'styles_root')
        {
            return null;
        }
        
        $object_path = '/' . implode('/', $args['variable_arguments']);
        if ($this->get_style($object_path))
        {
            return $this->style;
        }
        if ($this->get_elemet($object_path))
        {
            return $this->element;
        }
        
        return null;
    }

    public function action_webdav($route_id, &$data, $args)
    {
        if ($route_id == 'styles')
        {
            $this->object_path = '/' . implode('/', $args['variable_arguments']);
        }
        
        switch ($this->dispatcher->request_method)
        {
            case 'PROPFIND':
                $this->handle_propfind($route_id, $data);
                return;

            case 'GET':
                if ($this->get_element($this->object_path))
                {
                    $data['data'] = $this->element->value;
                    $data['mimetype'] = 'text/plain';
                    $data['mtime'] = strtotime($this->element->metadata->revised);
                    return;
                }
                elseif ($this->get_style($this->object_path))
                {
                    $data['mimetype'] = 'httpd/unix-directory'; 
                    $data['size'] = 0;
                    $data['mtime'] = strtotime($this->style->metadata->revised);
                    return;
                }

                throw new midgardmvc_exception_notfound("Style {$this->object_path} not found");

            case 'PUT':
                $this->handle_put($route_id, $data);
                return;

            case 'MKCOL':
                $this->handle_mkcol($route_id, $data);
                return;

            case 'MOVE':
                $this->handle_move($route_id, $data);
                return;

            case 'COPY':
                $this->handle_copy($route_id, $data);
                return;

            case 'DELETE':
                if ($this->get_element($this->object_path))
                {
                    $this->element->delete();
                    return;
                }
                elseif ($this->get_style($this->object_path))
                {
                    $this->style->delete();
                    return;
                }

                throw new midgardmvc_exception_notfound("Style {$this->object_path} not found");
                return;

            default:
                throw new midgardmvc_exception_httperror("{$this->dispatcher->request_method} not allowed", 405);
        }

    }
}
?>
