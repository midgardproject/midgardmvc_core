<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Tests for the configuration stack
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_tests_services_configuration extends midgardmvc_core_tests_testcase
{
    public function setUp()
    {
        $path = realpath(dirname(__FILE__)).'/../../configuration/defaults.yml';
        $this->testConfiguration = yaml_parse(file_get_contents($path));
        parent::setUp();
    }
    
    public function test_get()
    {
        foreach($this->testConfiguration as $key => $conf)
        {
            $this->assertEquals($this->_core->configuration->get($key), $this->testConfiguration[$key]);
        }
    }

    public function test_magic_getter()
    {
        foreach($this->testConfiguration as $key => $conf)
        {
            $this->assertEquals($this->_core->configuration->$key, $this->testConfiguration[$key]);
        }
    }

    public function test_exists()
    {
        foreach($this->testConfiguration as $key => $conf)
        {
            $this->assertTrue($this->_core->configuration->exists($key));
        }
    }

    public function test_isset()
    {
        foreach($this->testConfiguration as $key => $conf)
        {
            $this->assertTrue( isset($this->_core->configuration->$key));
        }
    }

    public function test_unserialize()
    {
        $path = realpath(dirname(__FILE__)).'/../../configuration/defaults.yml';
        $data = yaml_parse(file_get_contents($path));
        $data2 = $this->_core->configuration->unserialize(file_get_contents($path));
        if ($data === $data2)
        {
            $this->assertTrue(true);
        }
        else
        {
            $this->assertTrue(false);
        }
    }

    public function test_serialization()
    {
        $path = realpath(dirname(__FILE__)).'/../../configuration/defaults.yml';
        $data = yaml_parse(file_get_contents($path));
        $serialized = yaml_emit($data);
        $serialized2 = $this->_core->configuration->serialize($data);

        if ($serialized === $serialized2)
        {
            $this->assertTrue(true);
        }
        else
        {
            $this->assertTrue(false);
        }
    }

    public function test_merge_configs()
    {
        // flat
        $base = array('foo' => 'bar', 'baz' => 'bar');
        $extension = array('foo' => 'test', 'other' => 'test');

        $result = midgardmvc_core_services_configuration_yaml::merge_configs($base, $extension);
        $this->assertEquals(
            array('foo' => 'test', 'baz' => 'bar', 'other' => 'test'),
            $result
        );

        // 1-level
        $base = array('foo' => array('foo' => 'bar', 'baz' => 'bar'), 'baz' => '', 'more' => array());
        $extension = array('foo' => array('foo' => 'test', 'other' => 'test'), 'baz' => 'test', 'more' => array('foo' => 'test'));

        $result = midgardmvc_core_services_configuration_yaml::merge_configs($base, $extension);
        $this->assertEquals(
            array('foo' => array('foo' => 'test', 'baz' => 'bar', 'other' => 'test'), 'baz' => 'test', 'more' => array('foo' => 'test')),
            $result
        );
    }
    
    public function test_merge_config_routes()
    {
        $base = array
        (
            'foo' => 1,
            'routes' => array
            (
                'one' => array(),
                'two' => array
                (
                    'val' => false
                ),
            ),
        );
        
        $extension = array
        (
            'bar' => 1,
            'routes' => array
            (
                'three' => array(),
                'four' => array(),
                'two' => array
                (
                    'val' => true
                ),
            ),
        );   
        
        $result = midgardmvc_core_services_configuration_yaml::merge_configs($base, $extension);
        $i = 0;
        foreach ($result as $key => $val)
        {
            if ($key == 'foo')
            {
                $this->assertEquals($i, 0, '"foo" from base configuration should be first in the merged config');
            }

            if ($key == 'bar')
            {
                $this->assertEquals($i, 2, '"bar" from extended configuration should be third in the merged config');
            }

            if ($key == 'routes')
            {
                $ii = 0;
                foreach ($val as $route_id => $route_definition)
                {
                    if ($route_id == 'three')
                    {
                        $this->assertEquals($ii, 0, 'Route "three" should be first route in array');
                    }

                    if ($route_id == 'four')
                    {
                        $this->assertEquals($ii, 1, 'Route "four" should be second route in array');
                    }
 
                    if ($route_id == 'two')
                    {
                        $this->assertEquals($ii, 2, 'Route "two" should be third route in array');

                        $this->assertTrue($route_definition['val'], '"val" of route "two" should come from the extended configuration, not the base one');
                    }
 
                    if ($route_id == 'one')
                    {
                        $this->assertEquals($ii, 3, 'Route "one" should be fourth route in array ' . serialize($val));
                    }
                   
                    $ii++;
                }
            }
            $i++;
        }
    }
    
    public function test_contexts()
    {
        midgardmvc_core::get_instance()->configuration->load_component('midgardmvc_core');
        $initial_value = midgardmvc_core::get_instance()->configuration->get('log_level');
        
        // Then enter another context
        midgardmvc_core::get_instance()->context->create();
        $new_value = midgardmvc_core::get_instance()->configuration->get('log_level');
        midgardmvc_core::get_instance()->context->delete();
        
        $this->assertEquals($initial_value, $new_value);
    }
}
