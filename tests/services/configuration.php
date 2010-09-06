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
        $path = MIDGARDMVC_ROOT. '/midgardmvc_core/configuration/defaults.yml';
        $yaml = file_get_contents($path);
        if (!extension_loaded('yaml'))
        {
            // YAML PHP extension is not loaded, include the pure-PHP implementation
            require_once MIDGARDMVC_ROOT. '/midgardmvc_core/helpers/spyc.php';
            $this->testConfiguration = Spyc::YAMLLoad($yaml);
        }
        else
        {
            $this->testConfiguration = yaml_parse($yaml);
        }
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
