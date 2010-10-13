<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Test to see if contexts are working
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_tests_helpers_context extends midgardmvc_core_tests_testcase
{
    public function test_create()
    { 
        $original_context = $this->_core->context->get_current_context();
        $new_context = $original_context + 1;
        
        $request = new midgardmvc_core_request();
        $this->_core->context->create($request);
        $this->assertEquals($new_context, $this->_core->context->get_current_context());
        $this->_core->context->delete();
    }

    public function test_get()
    {
        $request = new midgardmvc_core_request();
        $this->_core->context->create($request);
        $current = $this->_core->context->get_current_context();
        
        try
        {
            $context = $this->_core->context->get($current);
        }
        catch (OutOfBoundsException $e)
        {
            $this->fail('An unexpected OutOfBoundsException has been raised.');
        }
        
        $this->assertTrue(is_array($context));
        
        $this->_core->context->delete();
    }
    
    public function test_delete()
    {
        $request = new midgardmvc_core_request();
        $this->_core->context->create($request);
        $current = $this->_core->context->get_current_context();
        $this->_core->context->delete();
        
        try
        {
            $context =& $this->_core->context->get($current);
        }
        catch (OutOfBoundsException $e)
        {
            return;
        }
        
        $this->fail('An expected OutOfBoundsException has not been raised.');
    }
    
    public function test_get_set()
    {
        $request = new midgardmvc_core_request();
        $this->_core->context->create($request);
        
        $this->_core->context->setted = true;
        
        $this->assertEquals(true, $this->_core->context->setted);
        
        $this->_core->context->delete();
    }

    public function test_magic()
    {
        $request = new midgardmvc_core_request();
        $this->_core->context->create($request);
        $this->assertFalse(isset($this->_core->context->missing_property));
        $this->_core->context->missing_property = 'foo';
        $this->assertTrue(isset($this->_core->context->missing_property));
        $this->assertEquals('foo', $this->_core->context->missing_property);
        $this->_core->context->delete();
        $this->assertFalse(isset($this->_core->context->missing_property));
    }
}
?>
