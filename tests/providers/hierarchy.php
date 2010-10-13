<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Tests for the hierarchy provider
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_tests_providers_hierarchy extends midgardmvc_core_tests_testcase
{
    public function test_get_node_by_path()
    {
        $node = midgardmvc_core::get_instance()->hierarchy->get_node_by_path('/foo/bar');
        $this->assertNotEquals(null, $node);
        $this->assertEquals('Midgard MVC', $node->title);
        $this->assertEquals('/', $node->get_path());
        $this->assertEquals(2, count($node->get_arguments()));

        $node = midgardmvc_core::get_instance()->hierarchy->get_node_by_path('/subdir/');
        $this->assertNotEquals(null, $node);
        $this->assertEquals('Subfolder', $node->title);
        $this->assertEquals('/subdir', $node->get_path());
        $this->assertEquals(0, count($node->get_arguments()));

        $node = midgardmvc_core::get_instance()->hierarchy->get_node_by_path('/subdir/foo');
        $this->assertNotEquals(null, $node);
        $this->assertEquals('Subfolder', $node->title);
        $this->assertEquals('/subdir', $node->get_path());
        $this->assertEquals(1, count($node->get_arguments()));
    }

    public function test_get_node_by_component()
    {
        $node = midgardmvc_core::get_instance()->hierarchy->get_node_by_component('midgardmvc_core');
        $this->assertNotEquals(null, $node);
        $this->assertEquals('Midgard MVC', $node->title);
    }

    public function test_get_node_by_component_missing()
    {
        $node = midgardmvc_core::get_instance()->hierarchy->get_node_by_component('missing_component');
        $this->assertEquals(null, $node);
    }

    public function test_has_child_nodes()
    {
        $node = midgardmvc_core::get_instance()->hierarchy->get_node_by_path('/subdir');
        $this->assertFalse($node->has_child_nodes());

        $node = midgardmvc_core::get_instance()->hierarchy->get_node_by_path('/');
        $this->assertTrue($node->has_child_nodes());
    }

    public function test_get_child_by_name()
    {
        $node = midgardmvc_core::get_instance()->hierarchy->get_node_by_path('/');
        $child = $node->get_child_by_name('foo');
        $this->assertEquals(null, $child);

        $child = $node->get_child_by_name('subdir');
        $this->assertEquals('/subdir/', $child->get_path());
    }

    public function test_get_child_nodes()
    {
        $node = midgardmvc_core::get_instance()->hierarchy->get_node_by_path('/subdir');
        $this->assertEquals(0, count($node->get_child_nodes()));

        $node = midgardmvc_core::get_instance()->hierarchy->get_node_by_path('/');
        $children = $node->get_child_nodes();
        $this->assertEquals(1, count($children));
        $node = midgardmvc_core::get_instance()->hierarchy->get_node_by_path('/subdir');
        $this->assertEquals('/subdir/', $children[0]->get_path());
    }
}
