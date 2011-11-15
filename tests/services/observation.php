<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Tests for the observation service
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_tests_services_observation extends midgardmvc_core_tests_testcase
{
    public function test_update_node()
    {
        $called = false;
        $root_node = midgardmvc_core::get_instance()->hierarchy->get_root_node()->get_object();
        if (get_class($root_node) != 'midgardmvc_core_node')
        {
            $this->markTestSkipped();
        }
        
        // Connect
        midgardmvc_core::get_instance()->observation->add_listener(
            function($object, $data) use ($called)
            {
                if ($object == midgardmvc_core::get_instance()->hierarchy->get_root_node()->get_object())
                {
                    $called = true;
                }
            }, 
            array('action-update'), 
            array(get_class($root_node))
        );
        
        $this->assertTrue($called);
    }

    public function test_load_person()
    {
        $called = 0;
        if (!class_exists('midgard_person'))
        {
            $this->markTestSkipped();
        }
        
        // Connect
        midgardmvc_core::get_instance()->observation->add_listener
        (
            function($object) use (&$called)
            {
                $called++;
            }, 
            array
            (
                'action-loaded-hook'
            ), 
            array('midgard_person')
        );

        $person = new midgard_person(1);   
        $person->update();
        
        $this->assertEquals(1, $called);
    }
    
    public function test_update_person()
    {
        $called = 0;
        if (!class_exists('midgard_person'))
        {
            $this->markTestSkipped();
        }
        
        // Connect
        midgardmvc_core::get_instance()->observation->add_listener
        (
            function($object) use (&$called)
            {
                $called++;
            }, 
            array
            (
                'action-update-hook',
                'action-update',
                'action-updated'
            ), 
            array('midgard_person')
        );

        $person = new midgard_person(1);   
        $person->update();
        
        $this->assertEquals(3, $called);
    }
}
