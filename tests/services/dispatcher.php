<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Tests for the dispatcher
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_tests_services_dispatcher extends midgardmvc_core_tests_testcase
{
    public function test_generate_url()
    {
        $url = midgardmvc_core::get_instance()->dispatcher->generate_url('index', array(), 'midgardmvc_core');
        $this->assertEquals('/', $url);
    }

    /**
     * @expectedException OutOfBoundsException
     */
    public function test_generate_url_missingroute()
    {
        $url = midgardmvc_core::get_instance()->dispatcher->generate_url('route_not_defined', array(), 'midgardmvc_core');
    }

    public function test_dispatch()
    {
        $request = midgardmvc_core_request::get_for_intent('/');
        $routes = midgardmvc_core::get_instance()->component->get_routes($request);
        $request->set_route($routes['index']);
        midgardmvc_core::get_instance()->dispatcher->dispatch($request);
        $this->assertTrue($request->isset_data_item('current_component'));
        $data = $request->get_data_item('current_component');
        $this->assertEquals('Midgard MVC', $data['object']->title);
    }

    public function test_dispatch_head()
    {
        $request = midgardmvc_core_request::get_for_intent('/');
        $routes = midgardmvc_core::get_instance()->component->get_routes($request);
        $request->set_route($routes['index']);
        $request->set_method('HEAD');
        midgardmvc_core::get_instance()->dispatcher->dispatch($request);
        $this->assertTrue($request->isset_data_item('current_component'));
        $data = $request->get_data_item('current_component');
        $this->assertEquals('Midgard MVC', $data['object']->title);
    }

    /**
     * @expectedException midgardmvc_exception_httperror
     */
    public function test_dispatch_invalidmethod()
    {
        $request = midgardmvc_core_request::get_for_intent('/');
        $routes = midgardmvc_core::get_instance()->component->get_routes($request);
        $request->set_route($routes['index']);
        $request->set_method('TRACE');
        midgardmvc_core::get_instance()->dispatcher->dispatch($request);
    }
}
