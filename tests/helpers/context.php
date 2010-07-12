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
    public function setUp()
    {        
        parent::setUp();
    }
    
    public function testCreate()
    { 
        $original_context = $this->_core->context->get_current_context();
        $new_context = $original_context + 1;
        
        $this->_core->context->create();        
        $context =& $this->_core->context->get();
        $this->assertEquals($this->_core->context->get_current_context(), $new_context);
        $this->assertTrue(!empty($context));
        
        $this->_core->context->delete();
    }

    public function testGet()
    {
        $this->_core->context->create();
        $current = $this->_core->context->get_current_context();
        
        $context = array();
        try
        {
            $context =& $this->_core->context->get($current);
        }
        catch (OutOfBoundsException $e)
        {
            $this->fail('An unexpected OutOfBoundsException has been raised.');
        }
        
        $this->assertTrue(!empty($context));
        
        $this->_core->context->delete();
    }
    
    public function testDelete()
    {
        $this->_core->context->create();
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
    
    public function testGetSet()
    {
        $this->_core->context->create();
        
        $this->_core->context->setted = true;
        
        $this->assertEquals($this->_core->context->setted, true);
        
        $this->_core->context->delete();
    }

    public function test_inherited_values()
    {
        $this->_core->context->root_page = new midgardmvc_core_node();
        $this->_core->context->root_page->id = 5;
        $this->_core->context->create();
        $this->assertEquals($this->_core->context->root_page->id, 5);
        $this->_core->context->delete();
    }
}
?>
