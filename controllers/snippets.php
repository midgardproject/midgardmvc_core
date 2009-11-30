<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * MidCOM Snippet and SnippetDir WebDAV management controller
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_controllers_snippets
{
    private $snippetdir = null;
    private $snippet = null;
    private $object_path = '';
    
    public function __construct($instance)
    {
        $this->configuration = $_MIDCOM->configuration;
    }
    
    private function get_snippetdir_children($snippetdir_id)
    {
        // Load children for PROPFIND purposes
        $children = array
        (
            array
            (
                'uri'      => "{$_MIDCOM->context->prefix}mgd:snippets{$this->object_path}/", // FIXME: dispatcher::generate_url
                'title'    => $this->object_path,
                'mimetype' => 'httpd/unix-directory',
                'resource' => 'collection',
            )
        );
        
        // Snippetdirs
        $mc = midgard_snippetdir::new_collector('up', $snippetdir_id);
        $mc->set_key_property('name');
        $mc->add_value_property('description');
        $mc->execute(); 
        $snippetdirs = $mc->list_keys();
        if (is_array($snippetdirs))
        {
            foreach ($snippetdirs as $name => $array)
            {
                if (empty($name))
                {
                    continue;
                }
                $children[] = array
                (
                    'uri'      => "{$_MIDCOM->context->prefix}mgd:snippets{$this->object_path}/{$name}/", // FIXME: dispatcher::generate_url
                    'title'    => $name,
                    'mimetype' => 'httpd/unix-directory',
                    'resource' => 'collection',
                );
            }
        }
        
        // Snippets
        $qb = new midgard_query_builder('midgard_snippet');
        $qb->add_constraint('up', '=', $snippetdir_id);
        $qb->add_constraint('name', '<>', '');
        $snippets = $qb->execute();
        if (is_array($snippets))
        {
            foreach ($snippets as $snippet)
            {
                $children[] = array
                (
                    'uri'      => "{$_MIDCOM->context->prefix}mgd:snippets{$this->object_path}/{$snippet->name}.php", // FIXME: dispatcher::generate_url
                    'title'    => $snippet->name,
                    'mimetype' => 'text/plain',
                    'size'     => $snippet->metadata->size,
                    'revised'  => $snippet->metadata->revised,
                );
            }
        }
        
        return $children;
    }
    
    private function get_snippetdir($object_path)
    {
        try
        {
            $snippetdir = new midgard_snippetdir();
            $snippetdir->get_by_path($object_path);
        }
        catch (midgard_error_exception $e)
        {
            return false;
        }
        $this->snippetdir = $snippetdir;
        return true;
    }
    
    private function get_snippet($object_path)
    {
        try
        {
            $snippet = new midgard_snippet();
            $snippet->get_by_path(str_replace('.php', '', $object_path));
        }
        catch (midgard_error_exception $e)
        {
            return false;
        }
        
        $this->snippet = $snippet;
        return true;
    }
    
    private function handle_propfind($route_id, &$data)
    {
        if ($route_id == 'snippets_root')
        {
            $data['children'] = $this->get_snippetdir_children(0);
            return;
        }
        
        if (!$this->get_snippetdir($this->object_path))
        {
            if (!$this->get_snippet($this->object_path))
            {
                throw new midgardmvc_exception_notfound("Snippetdir {$this->object_path} not found");
            }
            
            // Just put the snippet itself there
            $data['children'] = array
            (
                array
                (
                    'uri'      => "{$_MIDCOM->context->prefix}mgd:snippets{$this->object_path}", // FIXME: dispatcher::generate_url
                    'title'    => $this->snippet->name,
                    'mimetype' => 'text/plain',
                    'size'     => $this->snippet->metadata->size,
                    'revised'  => $this->snippet->metadata->revised,
                )
            );
            return;
        }
        
        $data['children'] = $this->get_snippetdir_children($this->snippetdir->id);
    }

    private function handle_put($route_id, &$data)
    {
        if (   $route_id == 'snippets_root'
            || $this->get_snippetdir($this->object_path))
        {
            throw new midgardmvc_exception_httperror("PUT to snippetdir not allowed", 405);
        }
        
        if (!$this->get_snippet($this->object_path))
        {
            $parent_path = dirname($this->object_path);
            if (!$this->get_snippetdir($parent_path))
            {
                throw new midgardmvc_exception_notfound("Snippetdir {$parent_path} not found");
            }
            $_MIDCOM->authorization->require_do('midgard:create', $this->snippetdir);
            
            $new_snippet = new midgard_snippet();
            $new_snippet->up = $this->snippetdir->id;
            $new_snippet->name = basename(str_replace('.php', '', $this->object_path));
            $new_snippet->create();
        }
        else
        {
            $new_snippet = $this->snippet;
        }
        
        $new_snippet->code = file_get_contents('php://input');
        $new_snippet->update();
    }

    private function handle_mkcol($route_id, &$data)
    {
        if (   $this->get_snippet
            || $this->snippetdir)
        {
            throw new midgardmvc_exception_httperror("MKCOL not allowed", 405);
        }
        
        $parent_path = dirname($this->object_path);
        if (   $parent_path != '/'
            && !$this->get_snippetdir($parent_path))
        {
            throw new midgardmvc_exception_notfound("Snippetdir {$parent_path} not found");
        }

        $new_snippetdir = new midgard_snippetdir();
        $new_snippetdir->name = basename($this->object_path);
        
        if ($parent_path != '/')
        {
            $_MIDCOM->authorization->require_do('midgard:create', $this->snippetdir);
            $new_snippetdir->up = $this->snippetdir->id;
        }
        
        $new_snippetdir->create();
    }

    private function check_destination($dest)
    {
        if (!isset($dest))
        {
            throw new Exception("No destination defined");
        }

        $snippets_prefix = 'mgd:snippets';
        if (substr($dest, 0, strlen($snippets_prefix)) != $snippets_prefix)
        {
            throw new Exception("Invalid destination {$dest}");
        }
        
        $destination_object_path = dirname(substr($dest, strlen($snippets_prefix)));
        if (!$this->get_snippetdir($destination_object_path))
        {
            throw new Exception("No snippetdir {$destination_object_path} found");
        }
        
        $destination['snippetdir'] = $this->snippetdir;
        $destination['name'] = basename($dest);
        
        return $destination;
    }

    private function handle_copy($route_id, &$data)
    {
        $destination = $this->check_destination($data['dest']);
        
        if (!$this->get_snippetdir($this->object_path))
        {
            // Possibly copying snippets instead
            if (!$this->get_snippet($this->object_path))
            {
                throw new midgardmvc_exception_notfound("Snippetdir {$this->object_path} not found");
            }

            $new_snippet = new midgard_snippet();
            $new_snippet->up = $destination['snippetdir']->id;
            $new_snippet->name = str_replace('.php', '', $destination['name']);
            $new_snippet->code = $this->snippet->code;
            $new_snippet->create();
            return;
        }

        $new_snippetdir = new midgard_snippetdir();
        $new_snippetdir->up = $destination['snippetdir']->id;
        $new_snippetdir->name = $destination['name'];
        $new_snippetdir->create();
    }

    private function handle_move($route_id, &$data)
    {
        $destination = $this->check_destination($data['dest']);
        
        if (!$this->get_snippetdir($this->object_path))
        {
            // Possibly moving snippets instead
            if (!$this->get_snippet($this->object_path))
            {
                throw new midgardmvc_exception_notfound("Snippetdir {$this->object_path} not found");
            }

            $this->snippet->up = $destination['snippetdir']->id;
            $this->snippet->name = str_replace('.php', '', $destination['name']);
            $this->snippet->update();
            return;
        }

        $this->snippetdir->up = $destination['snippetdir']->id;
        $this->snippetdir->name = $destination['name'];
        $this->snippetdir->update();
    }
    
    public function get_object_webdav($route_id, &$data, $args)
    {
        if ($route_id == 'snippets_root')
        {
            return null;
        }
        
        $object_path = '/' . implode('/', $args['variable_arguments']);
        //echo "{$object_path}\n";
        if ($this->get_snippetdir($object_path))
        {
            return $this->snippetdir;
        }
        if ($this->get_snippet($object_path))
        {
            return $this->snippet;
        }
        
        return null;
    }
    
    public function action_webdav($route_id, &$data, $args)
    {
        if ($route_id == 'snippets')
        {
            $this->object_path = '/' . implode('/', $args['variable_arguments']);
        }
        
        switch ($this->dispatcher->request_method)
        {
            case 'PROPFIND':
                $this->handle_propfind($route_id, $data);
                return;

            case 'GET':
                if ($this->get_snippetdir($this->object_path))
                {
                    $data['mimetype'] = 'httpd/unix-directory'; 
                    $data['size'] = 0;
                    $data['mtime'] = strtotime($this->snippetdir->metadata->revised);
                    return;
                }
                elseif ($this->get_snippet($this->object_path))
                {
                    $data['data'] = $this->snippet->code;
                    $data['mimetype'] = 'text/plain';
                    $data['mtime'] = strtotime($this->snippet->metadata->revised);
                    return;
                }

                throw new midgardmvc_exception_notfound("Snippetdir {$this->object_path} not found");

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
                if ($this->get_snippet($this->object_path))
                {
                    $this->snippet->delete();
                    return;
                }
                elseif ($this->get_snippetdir($this->object_path))
                {
                    $this->snippetdir->delete();
                    return;
                }

                throw new midgardmvc_exception_notfound("Snippetdir {$this->object_path} not found");
                return;

            default:
                throw new midgardmvc_exception_httperror("{$this->dispatcher->request_method} not allowed", 405);
        }

    }
}
?>
