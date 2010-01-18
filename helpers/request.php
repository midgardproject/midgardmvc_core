<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Midgard MVC HTTP request and URL mapping helper
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_helpers_request
{
    /**
     * HTTP verb used with request
     */
    private $method = 'get';

    /**
     * HTTP query parameters used with request
     */
    private $query = array();

    /**
     * The root page to be used with the request
     *
     * @var midgard_page
     */
    private $root_page = null;

    /**
     * The page to be used with the request
     *
     * @var midgard_page
     */
    private $page = null;

    /**
     * Midgard style to use with the request
     */
    public $style_id = 0;

    /**
     * Path to the page used with the request
     */
    public $path = '/';

    private $prefix = '/';
    
    private $component = 'midgardmvc_core';

    private $path_for_page = array();

    /**
     * URL parameters after page has been resolved
     */
    public $argv = array();

    public function __construct()
    {
    }

    /**
     * Match an URL path to a page. Remaining path arguments are stored to argv
     *
     * @param $path URL path
     */
    public function resolve_page($path)
    {
        if (   !is_string($path)
            || substr($path, 0, 1) != '/')
        {
            throw new InvalidArgumentException('Invalid path provided');
        }

        $temp = trim($path);
        $page = $this->root_page;
        $parent_id = $this->root_page->id;
        
        // Clean up path
        $path = substr(trim($path), 1);
        if (substr($path, strlen($path) - 1) == '/')
        {
            $path = substr($path, 0, -1);
        }
        if ($path == '')
        {
            $this->argv = array();
            $this->set_page($page);
            return;
        }
        
        $path = explode('/', $path);
        $this->argv = $path;        
        foreach ($path as $i => $p)
        {
            $qb = new midgard_query_builder('midgard_page');
            $qb->add_constraint('up', '=', $parent_id);
            $qb->add_constraint('name', '=', $p);
            $res = $qb->execute();
            if (count($res) != 1)
            {
                break;            
            }
            
            if ($res[0]->style)
            {
                $this->style_id = $res[0]->style;
            }
            
            $parent_id = $res[0]->id;
            $temp = substr($temp, 1 + strlen($p));
            $page = $res[0];
            $this->path .= $page->name . '/';
            array_shift($this->argv);
        }
        
        $this->path_for_page[$page->id] = $this->path;
        $this->set_page($page);
    }

    /**
     * Set a page to be used in the request
     */
    public function set_root_page(midgard_page $page)
    {
        $this->root_page = $page;
        
        $this->style_id = $page->style;
    }

    /**
     * Set a page to be used in the request
     */
    public function set_page(midgard_page $page)
    {
        $this->page = $page;
        
        if ($page->component)
        {
            $this->component = $page->component;
        }

        if (!isset($this->path_for_page[$page->id]))
        {
            if ($page->id == $this->root_page->id)
            {
                $path = '/';
            }
            else
            {
                $parent_page = $page;
                $path = "{$page->name}/";
                while (true)
                {
                    $parent_page = new midgard_page($parent_page->up);
                    if (   !$parent_page
                        || $parent_page->up == 0
                        || $parent_page->id == $this->root_page->id)
                    {
                        $path = "/{$path}";
                        break;
                    }
                    $path = "{$parent_page->name}/{$path}";
                }
            }
            $this->path_for_page[$page->id] = $path;
        }
        $this->path = $this->path_for_page[$page->id];
        if ($page->style)
        {
            $this->style_id = $page->style;
        }
    }

    public function get_page()
    {
        return $this->page;
    }

    public function get_component()
    {
        return $this->component;
    }

    public function set_argv(array $argv)
    {
        $this->argv = $argv;
    }
    
    public function get_argv()
    {
        return $this->argv;
    }
    
    public function set_method($method)
    {
        $this->method = $method;
    }
    
    public function get_method()
    {
        return $this->method;
    }
    
    public function set_query(array $get_params)
    {
        $this->query = $get_params;
    }
    
    public function get_query()
    {
        return $this->query;
    }

    public function set_prefix($prefix)
    {
        $this->prefix = $prefix;
    }
    
    public function get_prefix()
    {
        return $this->prefix;
    }
}
