<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Tests for the component provider
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_tests_providers_component extends midgardmvc_core_tests_testcase
{
    /**
     * @expectedException InvalidArgumentException
     */
    public function test_get_invalid()
    {
        midgardmvc_core::get_instance()->component->get(123);
    }

    /**
     * @expectedException OutOfRangeException
     */
    public function test_get_missing()
    {
        midgardmvc_core::get_instance()->component->get('invalid_component_name');
    }

    public function test_get()
    {
        $component = midgardmvc_core::get_instance()->component->get('midgardmvc_core');
        $this->assertTrue($component instanceof midgardmvc_core_providers_component_component);
    }

    public function test_is_installed()
    {
        $this->assertTrue(midgardmvc_core::get_instance()->component->is_installed('midgardmvc_core'));
        $this->assertFalse(midgardmvc_core::get_instance()->component->is_installed('invalid_component_name'));
    }

    public function test_get_components()
    {
        $components = midgardmvc_core::get_instance()->component->get_components();
        $this->assertTrue(is_array($components));
        $this->assertTrue($components[0] instanceof midgardmvc_core_providers_component_component);
    }

    public function test_get_routes()
    {
        $request = midgardmvc_core_request::get_for_intent('/');
        $routes = midgardmvc_core::get_instance()->component->get_routes($request);
        $this->assertTrue(is_array($routes));
        $this->assertTrue(isset($routes['index']));
        $this->assertTrue($routes['index'] instanceof midgardmvc_core_route);
        $this->assertTrue(isset($routes['login']), 'Root node should provide login route');

        $request = midgardmvc_core_request::get_for_intent('/subdir');
        $routes = midgardmvc_core::get_instance()->component->get_routes($request);
        $this->assertTrue(is_array($routes));
        $this->assertTrue(isset($routes['index']));
        $this->assertTrue($routes['index'] instanceof midgardmvc_core_route);
        $this->assertFalse(isset($routes['login']), 'Subnode should not provide login route');
    }

    public function test_get_classes()
    {
        $component = midgardmvc_core::get_instance()->component->get('midgardmvc_core');
        $classes = $component->get_classes();
        $this->assertTrue(in_array('midgardmvc_core', $classes), 'Test that we know of the midgardmvc_core class');
        $this->assertTrue(in_array('midgardmvc_core_helpers_context', $classes), 'Test that we know of the context class');
        $this->assertFalse(in_array('midgardmvc_admin_injector', $classes));
    }

    public function test_get_class_contents()
    {
        $component = midgardmvc_core::get_instance()->component->get('midgardmvc_core');
        $original = file_get_contents(MIDGARDMVC_ROOT . "/midgardmvc_core/request.php");
        $this->assertEquals($original, $component->get_class_contents('midgardmvc_core_request'));

        $this->assertEquals(null, $component->get_class_contents('midgardmvc_core_request_not_defined'));
    }

    public function test_get_description()
    {
        $component = midgardmvc_core::get_instance()->component->get('midgardmvc_core');
        $original = file_get_contents(MIDGARDMVC_ROOT . "/midgardmvc_core/README.markdown");
        $this->assertEquals($original, $component->get_description());
    }
}
