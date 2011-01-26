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
class midgardmvc_core_providers_hierarchy_node_midgard2 implements midgardmvc_core_providers_hierarchy_node
{
    private $node = null;
    private $argv = array();
    private $path = null;
    private $children = array();
    private $configuration = null;

    public $name = '';
    public $title = '';
    public $content = '';

    /**
     * List of nodes indexed by database ID
     */
    static $nodes = array();
    static $nodes_by_component = array();

    public function __construct(midgardmvc_core_node $node)
    {
        $this->refresh_node($node);
    }

    public function refresh_node(midgardmvc_core_node $node)
    {
        $this->node = $node;
        $this->name =& $node->name;
        $this->title =& $node->title;
        $this->content =& $node->content;
        $this->path = null;
    }

    public function get_object()
    {
        return $this->node;
    }

    public function get_configuration()
    {
        if (is_null($this->configuration))
        {
            $configuration_param = $this->node->get_parameter('midgardmvc_core', 'configuration');
            $this->configuration = array();
            if ($configuration_param)
            {
                $this->configuration = midgardmvc_core::read_yaml($configuration_param);
            }
        }

        return $this->configuration;
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
        if (is_null($this->path))
        {
            $parent = $this->get_parent_node();
            if (!$parent)
            {
                $this->path = '/';
            }
            else
            {
                $parent_path = $parent->get_path();
                if (substr($parent_path, -1, 1) != '/')
                {
                    $parent_path .= '/';
                }
                $this->path = "{$parent_path}{$this->name}/";
            }
        }
        return $this->path;
    }

    public function set_path($path)
    {
        $this->path = $path;
    }

    public function get_child_nodes()
    {
        if (!empty($this->children))
        {
            return $this->children;
        }
        $qb = new midgard_query_builder('midgardmvc_core_node');
        $qb->add_constraint('up', '=', $this->node->id);
        $nodes = $qb->execute();
        foreach ($nodes as $node)
        {
            $this->children[] = self::get_instance($node);
        }
        return $this->children;
    }

    public function get_child_by_name($name)
    {
        $children = $this->get_child_nodes();
        foreach ($children as $child)
        {
            if ($child->name == $name)
            {
                return $child;
            }
        }
        return null;
    }

    public function has_child_nodes()
    {
        if (!empty($this->children))
        {
            // We already know this from cache
            return true;
        }

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

        if (!isset(midgardmvc_core_providers_hierarchy_node_midgard2::$nodes[$this->node->up]))
        {
            // Get from database
            $node = new midgardmvc_core_node($this->node->up);
            return self::get_instance($node);
        }

        // Get from local cache
        return self::$nodes[$this->node->up];
    }

    public static function get_instance(midgardmvc_core_node $node)
    {
        if (isset(self::$nodes[$node->id]))
        {
            return self::$nodes[$node->id];
        }
        self::$nodes[$node->id] = new midgardmvc_core_providers_hierarchy_node_midgard2($node);

        if (!isset(self::$nodes_by_component[$node->component]))
        {
            self::$nodes_by_component[$node->component] = self::$nodes[$node->id];
        }

        return self::$nodes[$node->id];
    }
}
