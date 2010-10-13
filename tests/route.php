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

    public function test_set_variables()
    {
        $route = new midgardmvc_core_route('page_read', '/foo/{$bar}/{$int:baz}', 'foo', 'bar', array());
        $path = $route->set_variables(array('bar' => 'foo', 'baz' => 'bar'));
        $this->assertEquals('/foo/foo/bar', implode('/', $path));
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

    public function test_check_match_typedvar()
    {
        $route = new midgardmvc_core_route('page_read', '/foo/{$bar}/{$int:baz}', 'foo', 'bar', array());
        $matched = $route->check_match('/foo/baz/7/');
        $this->assertTrue(isset($matched['bar']));
        $this->assertEquals('baz', $matched['bar']);
        $this->assertEquals(7, $matched['baz']);
        $this->assertType('int', $matched['baz']);

        $unmatched = $route->check_match('/foobar/baz/five/');
        $this->assertEquals(null, $unmatched);

        $unmatched = $route->check_match('/foobar/baz');
        $this->assertEquals(null, $unmatched);
    }

    public function test_check_match_tokenvar()
    {
        $route = new midgardmvc_core_route('page_read', '/foo/{$bar}/{$token:baz}', 'foo', 'bar', array());
        // Simple variant: just identifier given
        $matched = $route->check_match('/foo/baz/7/');
        $this->assertTrue(isset($matched['bar']));
        $this->assertEquals('baz', $matched['bar']);
        $this->assertTrue(is_array($matched['baz']));
        $this->assertEquals(7, $matched['baz']['identifier']);
        $this->assertEquals('html', $matched['baz']['type']);

        // More complex variant requested: identifier and type
        $matched = $route->check_match('/foo/baz/article.json/');
        $this->assertTrue(isset($matched['bar']));
        $this->assertEquals('baz', $matched['bar']);
        $this->assertTrue(is_array($matched['baz']));
        $this->assertEquals('article', $matched['baz']['identifier']);
        $this->assertEquals('json', $matched['baz']['type']);

        // More complex variant requested: identifier, variant and type
        $matched = $route->check_match('/foo/baz/article.title.json/');
        $this->assertTrue(isset($matched['bar']));
        $this->assertEquals('baz', $matched['bar']);
        $this->assertTrue(is_array($matched['baz']));
        $this->assertEquals('article', $matched['baz']['identifier']);
        $this->assertEquals('title', $matched['baz']['variant']);
        $this->assertEquals('json', $matched['baz']['type']);

        // More complex variant requested: identifier, variant, language and type
        $matched = $route->check_match('/foo/baz/article.title.en.json/');
        $this->assertTrue(isset($matched['bar']));
        $this->assertEquals('baz', $matched['bar']);
        $this->assertTrue(is_array($matched['baz']));
        $this->assertEquals('article', $matched['baz']['identifier']);
        $this->assertEquals('title', $matched['baz']['variant']);
        $this->assertEquals('en', $matched['baz']['language']);
        $this->assertEquals('json', $matched['baz']['type']);
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function test_check_match_invalid_get()
    {
        $route = new midgardmvc_core_route('page_read', '/foo/?bar=', 'foo', 'bar', array());
        $matched = $route->check_match('/foo/', array('bar' => 'baz'));
    }

    public function test_check_match_get()
    {
        $route = new midgardmvc_core_route('page_read', '/foo/?bar={$baz}', 'foo', 'bar', array());
        $matched = $route->check_match('/foo/', array('bar' => 'baz'));
        $this->assertTrue(isset($matched['bar']));
        $this->assertEquals('baz', $matched['bar']);

        $unmatched = $route->check_match('/foo/baz/');
        $this->assertEquals(null, $unmatched);

        $unmatched = $route->check_match('/foo/');
        $this->assertEquals(null, $unmatched);
    }

    public function test_check_match_simplevar_get()
    {
        $route = new midgardmvc_core_route('page_read', '/foo/{$foo}/?bar={$baz}', 'foo', 'bar', array());
        $matched = $route->check_match('/foo/bar/', array('bar' => 'baz'));
        $this->assertTrue(isset($matched['foo']));
        $this->assertTrue(isset($matched['bar']));
        $this->assertEquals('bar', $matched['foo']);
        $this->assertEquals('baz', $matched['bar']);

        $unmatched = $route->check_match('/foo/baz/');
        $this->assertEquals(null, $unmatched);

        $unmatched = $route->check_match('/foo/baz/', array('baz' => 'bar'));
        $this->assertEquals(null, $unmatched);
    }

    public function test_check_match_array_simple()
    {
        $route = new midgardmvc_core_route('page_read', '/foo@', 'foo', 'bar', array());
        $matched = $route->check_match('/foo');
        $this->assertEquals(null, $matched);

        $matched = $route->check_match('/foo/bar/baz/');
        $this->assertTrue(is_array($matched));
        $this->assertTrue(isset($matched['variable_arguments']));
        $this->assertEquals('bar', $matched['variable_arguments'][0]);
    }

    public function test_check_match_array_var()
    {
        $route = new midgardmvc_core_route('page_read', '/foo/{$bar}/baz@', 'foo', 'bar', array());
        $matched = $route->check_match('/foo/news/');
        $this->assertEquals(null, $matched);

        $matched = $route->check_match('/foo/news/baz/bar/');
        $this->assertTrue(is_array($matched));
        $this->assertTrue(isset($matched['bar']));
        $this->assertEquals('news', $matched['bar']);
        $this->assertTrue(isset($matched['variable_arguments']));
        $this->assertEquals('bar', $matched['variable_arguments'][0]);
    }
}
?>
