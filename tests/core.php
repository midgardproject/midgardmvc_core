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

    public function test_unknown_service()
    {
        $midgardmvc = midgardmvc_core::get_instance();
        try
        {
            $newservice = $midgardmvc->missingservice;
        }
        catch (InvalidArgumentException $e)
        {
            return;
        }
        
        $this->fail('An expected InvalidArgumentException has not been raised.');
    }

    public function test_read_yaml()
    {
        $yaml = 'foo: bar';
        $parsed = midgardmvc_core::read_yaml($yaml);
        $this->assertTrue(is_array($parsed));
        $this->assertTrue(isset($parsed['foo']));
        $this->assertEquals('bar', $parsed['foo']);
    }
}
?>
