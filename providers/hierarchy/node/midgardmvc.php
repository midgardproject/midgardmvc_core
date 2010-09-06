<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Midgard MVC hierarchy node from database
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_providers_hierarchy_node_midgardmvc implements midgardmvc_core_providers_hierarchy_node
{
    private $node = null;
    private $argv = array();
    private $path = '/';

    public $name = '';
    public $title = '';
    public $content = '';

    public function __construct(midgardmvc_core_node $node)
    {
        $this->node = $node;
        $this->name =& $node->name;
        $this->title =& $node->title;
        $this->content =& $node->content;
    }

    public function get_object()
    {
        return $this->node;
    }

    public function get_component()
    {
        return $this->node->component;
    }

    public function get_arguments()
    {
        return $this->argv;
    }

    public function set_arguments(array $argv)
    {
        $this->argv = $argv;
    }

    public function get_path()
    {
        return $this->path;
    }

    public function set_path($path)
    {
        $this->path = $path;
    }

    public function get_child_nodes()
    {
        $children = array();
        $qb = new midgard_query_builder('midgardmvc_core_node');
        $qb->add_constraint('up', '=', $this->node->id);
        $nodes = $qb->execute();
        foreach ($nodes as $node)
        {
            $children[] = new midgardmvc_core_providers_hierarchy_node_midgardmvc($node);
        }
        return $children;
    }

    public function get_child_by_name($name)
    {
        $qb = new midgard_query_builder('midgardmvc_core_node');
        $qb->add_constraint('up', '=', $this->node->id);
        $qb->add_constraint('name', '=', $name);
        $qb->set_limit(1);
        $nodes = $qb->execute();
        if (count($nodes) == 0)
        {
            return null;
        }
        $node = new midgardmvc_core_providers_hierarchy_node_midgardmvc($nodes[0]);
        return $node;
    }

    public function has_child_nodes()
    {
        $qb = new midgard_query_builder('midgardmvc_core_node');
        $qb->add_constraint('up', '=', $this->node->id);
        if ($qb->count() > 0)
        {
            return true;
        }
        return false;
    }

    public function get_parent_node()
    {
        if ($this->node->up == 0)
        {
            return null;
        }
        $node = new midgardmvc_core_node($this->node->up);
        $parent = new midgardmvc_core_providers_hierarchy_node_midgardmvc($node);
        return $parent;
    }
}