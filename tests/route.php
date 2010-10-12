<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Unit tests for the route class
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_tests_route extends PHPUnit_FrameWork_TestCase
{
    public function test_default_templates()
    {
        $route = new midgardmvc_core_route('page_read', '/', 'foo', 'bar', array());
        $this->assertEquals('ROOT', $route->template_aliases['root']);
        $this->assertEquals('', $route->template_aliases['content']);
    }

    public function test_overridden_templates()
    {
        $route = new midgardmvc_core_route('page_read', '/', 'foo', 'bar', array('foo' => 'bar', 'root' => 'baz'));
        $this->assertEquals('baz', $route->template_aliases['root']);
        $this->assertEquals('', $route->template_aliases['content']);
        $this->assertEquals('bar', $route->template_aliases['foo']);
    }

    public function test_check_match_simple()
    {
        $route = new midgardmvc_core_route('page_read', '/', 'foo', 'bar', array());
        $matched = $route->check_match('/');
        $this->assertEquals(array(), $matched);
    }

    public function test_check_match_simplevar()
    {
        $route = new midgardmvc_core_route('page_read', '/foo/{$bar}', 'foo', 'bar', array());
        $matched = $route->check_match('/foo/baz/');
        $this->assertTrue(isset($matched['bar']));
        $this->assertEquals('baz', $matched['bar']);

        $unmatched = $route->check_match('/foobar/baz/');
        $this->assertEquals(null, $unmatched);

        $unmatched = $route->check_match('/foobar/baz');
        $this->assertEquals(null, $unmatched);
    }
}
?>
