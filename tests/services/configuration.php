<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

require_once(dirname(__FILE__) . '/../../../tests/testcase.php');

/**
 * Test to see if contexts are working
 */
class midgardmvc_core_tests_services_configuration extends midgardmvc_tests_testcase
{
    public function setUp()
    {
        $path = realpath(dirname(__FILE__)).'/../../configuration/defaults.yml';
        $this->testConfiguration = syck_load(file_get_contents($path));
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
        $data = syck_load(file_get_contents($path));
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
        $data = syck_load(file_get_contents($path));
        $serialized = syck_dump($data);
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
                'two' => array(),
            ),
        );
        
        $extension = array
        (
            'bar' => 1,
            'routes' => array
            (
                'three' => array(),
                'four' => array(),
            ),
        );   
        
        $result = midgardmvc_core_services_configuration_yaml::merge_configs($base, $extension);
        
        foreach ($result as $key => $val)
        {
            if ($val == 'foo')
            {
                $this->assertEquals($key, 0);
                continue;
            }

            if ($val == 'bar')
            {
                $this->assertEquals($key, 2);
                continue;
            }

            if ($val == 'routes')
            {
                foreach ($val as $key2 => $val2)
                {
                    if ($key == 'three')
                    {
                        $this->assertEquals($key2, 0);
                        continue;
                    }
                    
                    if ($key == 'one')
                    {
                        $this->assertEquals($key2, 2);
                    }
                }
            }
        }
    }
}
