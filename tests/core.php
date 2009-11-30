<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

require_once(dirname(__FILE__) . '/../../tests/testcase.php');

/**
 * Test to see if midgardmvc_core is working
 */
class midgardmvc_core_tests_core extends midcom_tests_testcase
{
    public function test_singleton()
    {
        $this->_core->newproperty = true;
        $midcom_new = midgardmvc_core::get_instance();
        $this->assertEquals($midcom_new->newproperty, true);
        unset($this->_core->newproperty);
    }

    public function test_known_services_match()
    {
        $services = array
        (
            'authentication',
            'authorization',
            'templating',
            'cache',
        );
        
        foreach ($services as $service)
        {
            $service_instance = $this->_core->$service;
            $service_interface = "midgardmvc_core_services_{$service}";
            $this->assertTrue($service_instance instanceof $service_interface);
        }
    }

    public function test_unknown_service()
    {
        try
        {
            $newservice = $this->_core->missingservice;
        }
        catch (InvalidArgumentException $e)
        {
            return;
        }
        
        $this->fail('An expected InvalidArgumentException has not been raised.');
    }
}
?>
