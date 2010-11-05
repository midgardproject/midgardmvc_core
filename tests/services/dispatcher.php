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
    
    public function test_generate_url_intvariable()
    {
        $now = (int)time();
        $url = midgardmvc_core::get_instance()->dispatcher->generate_url('integer_variable_test_route', array('test_variable' => $now), 'midgardmvc_core');
        $this->assertEquals("/{$now}/", $url);
        
        $now = "{$now}";
        $url = midgardmvc_core::get_instance()->dispatcher->generate_url('integer_variable_test_route', array('test_variable' => $now), 'midgardmvc_core');
        $this->assertNotEquals("/{$now}/", $url);
    }

    public function test_generate_url_floatvariable()
    {
        $now = (float)time()/2;
        $url = midgardmvc_core::get_instance()->dispatcher->generate_url('float_variable_test_route', array('test_variable' => $now), 'midgardmvc_core');
        $this->assertEquals("/{$now}/", $url);

        $now = "{$now}";
        $url = midgardmvc_core::get_instance()->dispatcher->generate_url('float_variable_test_route', array('test_variable' => $now), 'midgardmvc_core');
        $this->assertNotEquals("/{$now}/", $url);
    }

    public function test_generate_url_variable()
    {
        $now = time();
        
        $url = midgardmvc_core::get_instance()->dispatcher->generate_url('variable_test_route', array('test_variable' => $now), 'midgardmvc_core');
        $this->assertEquals("/{$now}/", $url);

        $now = "{$now}"; // Typecasting to string
        $url = midgardmvc_core::get_instance()->dispatcher->generate_url('variable_test_route', array('test_variable' => $now), 'midgardmvc_core');
        $this->assertEquals("/{$now}/", $url);
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
