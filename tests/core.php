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

    public function test_known_services_match()
    {
        $midgardmvc = midgardmvc_core::get_instance();
        $services = array
        (
            'authentication',
            //'authorization',
            'templating',
            //'cache',
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
}
?>
