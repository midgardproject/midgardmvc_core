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
        $this->assertTrue(isset($routes['page_read']));
        $this->assertTrue($routes['page_read'] instanceof midgardmvc_core_route);
    }
}
