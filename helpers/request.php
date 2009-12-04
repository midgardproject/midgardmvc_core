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

    /**
     * URL parameters after page has been resolved
     */
    public $argv = array();

    public function __construct()
    {
        $this->midgardmvc = midgardmvc_core::get_instance();
    }

    /**
     * Match an URL path to a page. Remaining path arguments are stored to argv
     *
     * @param $path URL path
     * @return midgard_page
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
            return $page;
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

        return $page;
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
        
        if ($page->style)
        {
            $this->style_id = $page->style;
        }
    }
}
