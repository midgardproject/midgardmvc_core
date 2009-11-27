<?php
/**
 * @package midcom_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

require_once(dirname(__FILE__) . '/../../../tests/testcase.php');

/**
 * Test to see if contexts are working
 */
class midcom_core_tests_component_loader extends midcom_tests_testcase
{   

    public function setUp()
    {
        parent::setUp();
        $this->loader = new midcom_core_component_loader();
    }

    public function test_can_load_nonexisting_component()
    {
        $loader = new midcom_core_component_loader();
        $this->assertTrue( !$loader->can_load(md5(time())));
    }
    
    public function test_load_nonexisting_component()
    {
        $loader = new midcom_core_component_loader();
        $this->assertTrue( !$loader->load(md5(time())));
    }

    
    public function test_can_load_nonexisting_component_twice()
    {
        $loader = new midcom_core_component_loader();
        $component_name = md5(time());
        $this->assertTrue( !$loader->can_load($component_name));
        $this->assertTrue( !$loader->can_load($component_name));
    }
    
    public function test_load_manifests()
    {
        $this->loader->manifests = null;
        $this->loader->__construct();
        $this->assertTrue( is_array($this->loader->manifests));
    }
    
    public function test_load_invalid_characters_in_name()
    {
        $this->loader->manifests[] = 'invalid()name';
        $this->assertTrue( !$this->loader->load('invalid()name'));
    }
    
    public function test_component_to_filepath()
    {
        $filepath_correct = MIDGARDMVC_ROOT . '/' . 'midcom_core';
        $filepath_loader = $this->loader->component_to_filepath('midcom_core');
        $this->assertEquals($filepath_loader, $filepath_correct);
    }
    
    public function test_unknown_component_to_filepath()
    {
        try
        {
            $filepath_loader = $this->loader->component_to_filepath('net_example_missingcomponent');
        }
        catch (OutOfRangeException $e)
        {
            return;
        }
        
        $this->fail('An expected OutOfRangeException has not been raised.');
    }

    public function test_can_load_component()
    {
        $this->assertTrue($this->loader->can_load('midcom_core'));
        
        // Run it a second time to test caching
        $this->assertTrue($this->loader->can_load('midcom_core'));
    }

    public function test_can_load_unknown_component()
    {
        $this->assertFalse($this->loader->can_load('net_example_missingcomponent'));
    }

    public function test_load_component()
    {
        $interface = $this->loader->load('midcom_core');
        $this->assertTrue(is_a($interface, 'midcom_core_component_interface'));
        
        // Run it a second time to test caching
        $interface = $this->loader->load('midcom_core');
        $this->assertTrue(is_a($interface, 'midcom_core_component_interface'));
    }

    public function test_load_unknown_component()
    {
        $interface = $this->loader->load('net_example_missingcomponent');
        $this->assertFalse($interface);
    }
}
?>
