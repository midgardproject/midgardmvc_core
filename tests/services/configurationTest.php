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
class midgardmvc_core_tests_services_configurationTest extends midgardmvc_core_tests_testcase
{
    public function setUp()
    {
        $path = midgardmvc_core::get_component_path('midgardmvc_core') . '/configuration/defaults.yml';
        $yaml = file_get_contents($path);
        $this->testConfiguration = midgardmvc_core::read_yaml($yaml);
        parent::setUp();
    }
    
    public function test_get()
    {
        foreach($this->testConfiguration as $key => $conf)
        {
            if (isset($this->local_configuration[$key]))
            {
                // These configuration values are overridden in test suite, ignore
                continue;
            }
            $this->assertEquals($this->testConfiguration[$key], $this->_core->configuration->get($key), "Getter for configuration key {$key}");
        }
    }

    public function test_magic_getter()
    {
        foreach($this->testConfiguration as $key => $conf)
        {
            if (isset($this->local_configuration[$key]))
            {
                // These configuration values are overridden in test suite, ignore
                continue;
            }
            $this->assertEquals($this->testConfiguration[$key], $this->_core->configuration->$key, "Magic getter for configuration key {$key}");
        }
    }

    public function test_exists()
    {
        foreach($this->testConfiguration as $key => $conf)
        {
            $this->assertTrue($this->_core->configuration->exists($key), "Test whether {$key} exists in configuration");
        }
    }

    public function test_isset()
    {
        foreach($this->testConfiguration as $key => $conf)
        {
            $this->assertTrue(isset($this->_core->configuration->$key), "Test whether {$key} is set in configuration");
        }
    }
    
    public function test_contexts()
    {
        $initial_value = midgardmvc_core::get_instance()->configuration->get('services_authentication');
        
        // Then enter another context
        $request = new midgardmvc_core_request();
        midgardmvc_core::get_instance()->context->create($request);
        $new_value = midgardmvc_core::get_instance()->configuration->get('services_authentication');
        midgardmvc_core::get_instance()->context->delete();
        
        $this->assertEquals($initial_value, $new_value);
    }
}
