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
class midgardmvc_core_tests_coreTest extends PHPUnit_Framework_TestCase
{
    private $configuration = array
    (
        'services_dispatcher' => 'manual',
        'providers_component' => 'midgardmvc',
        'providers_hierarchy' => 'configuration',
        'components' => array
        (
            'midgardmvc_core' => true,
        ),
        'nodes' => array
        (
            'title' => 'Midgard MVC',
            'content' => '<p>Welcome to Midgard MVC</p>',
            'component' => 'midgardmvc_core',
        ),
    );

    public function setUp()
    {
        midgardmvc_core::get_instance($this->configuration);
        parent::setUp();
    }

    public function tearDown()
    {
        midgardmvc_core::clear_instance();
        parent::tearDown();
    }

    public function test_singleton()
    {
        $midgardmvc = midgardmvc_core::get_instance();
        $midgardmvc->newproperty = true;
        $midgardmvc_new = midgardmvc_core::get_instance();
        $this->assertEquals(true, $midgardmvc_new->newproperty);
        unset($midgardmvc->newproperty);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_singleton_nullable()
    {
        $midgardmvc = midgardmvc_core::get_instance();
        $midgardmvc->newproperty = true;
        $midgardmvc_new = midgardmvc_core::get_instance();
        $this->assertEquals(true, $midgardmvc_new->newproperty);

        midgardmvc_core::clear_instance();

        $midgardmvc = midgardmvc_core::get_instance($this->configuration);
        $midgardmvc->newproperty;
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
            'templating',
        );

        if (extension_loaded('midgard2')) 
        {
            $services[] = 'authentication';
        }
        
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
        $request->set_route($routes['index']);
        midgardmvc_core::get_instance()->dispatcher->set_request($request);
        $request = midgardmvc_core::get_instance()->process();
        $this->assertTrue($request->isset_data_item('current_component'));
        $data = $request->get_data_item('current_component');
        $this->assertEquals('Midgard MVC', $data['object']->title);

        $this->assertTrue($request->isset_data_item('midgardmvc_core'));
        $data = $request->get_data_item('midgardmvc_core');
        $this->assertEquals('Midgard MVC', $data['object']->title);
    }

    /**
     * @expectedException midgardmvc_exception_notfound
     */
    public function test_process_notfound()
    {
        $request = midgardmvc_core_request::get_for_intent('/');
        $routes = midgardmvc_core::get_instance()->component->get_routes($request);
        $request->set_route($routes['index']);
        $request->set_arguments(array('foo' => 'bar'));
        midgardmvc_core::get_instance()->dispatcher->set_request($request);
        $request = midgardmvc_core::get_instance()->process();
    }

    public function test_serve()
    {
        $_ENV['MIDGARD_ENV_GLOBAL_CACHEDIR'] = '/tmp';
        $request = midgardmvc_core_request::get_for_intent('/');
        $routes = midgardmvc_core::get_instance()->component->get_routes($request);
        $request->set_route($routes['index']);
        midgardmvc_core::get_instance()->dispatcher->set_request($request);
        $request = midgardmvc_core::get_instance()->process();
        ob_start();
        midgardmvc_core::get_instance()->serve($request);
        $content = ob_get_clean();
        $this->assertTrue(strpos($content, '<h1 property="mgd:title">Midgard MVC</h1>') !== false);
    }
}
?>
