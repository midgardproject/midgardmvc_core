<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Test to see if midgardmvc_core is working
 */
class midgardmvc_core_tests_core extends PHPUnit_FrameWork_TestCase
{
    public function test_singleton()
    {
        $midgardmvc = midgardmvc_core::get_instance
        (
            array
            (
                'services_dispatcher' => 'manual',
            )
        );
        $midgardmvc->newproperty = true;
        $midgardmvc_new = midgardmvc_core::get_instance();
        $this->assertEquals(true, $midgardmvc_new->newproperty);
        unset($midgardmvc->newproperty);
    }

    public function test_known_providers_match()
    {
        $midgardmvc = midgardmvc_core::get_instance();
        $providers = array
        (
            'hierarchy',
            'component',
        );

        foreach ($providers as $provider)
        {
            $provider_instance = $midgardmvc->$provider;
            $provider_interface = "midgardmvc_core_providers_{$provider}";
            $this->assertTrue($provider_instance instanceof $provider_interface);
        }
    }

    public function test_known_services_match()
    {
        $midgardmvc = midgardmvc_core::get_instance();
        $services = array
        (
            'authentication',
            'templating',
        );
        
        foreach ($services as $service)
        {
            $service_instance = $midgardmvc->$service;
            $service_interface = "midgardmvc_core_services_{$service}";
            $this->assertTrue($service_instance instanceof $service_interface);
        }
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_unknown_service()
    {
        $midgardmvc = midgardmvc_core::get_instance();
        $newservice = $midgardmvc->missingservice;
    }

    public function test_read_yaml()
    {
        $yaml = 'foo: bar';
        $parsed = midgardmvc_core::read_yaml($yaml);
        $this->assertTrue(is_array($parsed));
        $this->assertTrue(isset($parsed['foo']));
        $this->assertEquals('bar', $parsed['foo']);
    }

    public function test_read_yaml_empty()
    {
        $yaml = '';
        $parsed = midgardmvc_core::read_yaml($yaml);
        $this->assertTrue(is_array($parsed));
        $this->assertTrue(empty($parsed));
    }

    public function test_process()
    {
        $request = midgardmvc_core_request::get_for_intent('/');
        $routes = midgardmvc_core::get_instance()->component->get_routes($request);
        $request->set_route($routes['page_read']);
        midgardmvc_core::get_instance()->dispatcher->set_request($request);
        $request = midgardmvc_core::get_instance()->process();
    }

    /**
     * @expectedException midgardmvc_exception_notfound
     */
    public function test_process_notfound()
    {
        $request = midgardmvc_core_request::get_for_intent('/');
        $routes = midgardmvc_core::get_instance()->component->get_routes($request);
        $request->set_route($routes['page_read']);
        $request->set_arguments(array('foo' => 'bar'));
        midgardmvc_core::get_instance()->dispatcher->set_request($request);
        $request = midgardmvc_core::get_instance()->process();
    }

    public function test_serve()
    {
        $_ENV['MIDGARD_ENV_GLOBAL_CACHEDIR'] = '/tmp';
        $request = midgardmvc_core_request::get_for_intent('/');
        $routes = midgardmvc_core::get_instance()->component->get_routes($request);
        $request->set_route($routes['page_read']);
        midgardmvc_core::get_instance()->dispatcher->set_request($request);
        $request = midgardmvc_core::get_instance()->process();
        ob_start();
        midgardmvc_core::get_instance()->serve($request);
        $content = ob_get_clean();
        $this->assertTrue(strpos($content, '<h1 mgd:property="title">Midgard MVC</h1>') !== false);
    }
}
?>
