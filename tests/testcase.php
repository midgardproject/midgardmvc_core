<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * This class provides the setUp and tearDown methods needed for unit testing classes
 * that expect to have a working Midgard MVC environment available.
 *
 * @package midgardmvc_core
 */
abstract class midgardmvc_core_tests_testcase extends midgardmvc_core_tests_midgard
{
    protected $_core;
    protected static $database_created = false;
    
    public function setUp()
    {
        // Create database and open connection
        parent::setUp();

        $this->_core = midgardmvc_core::get_instance
        (
            array
            (
                'services_dispatcher' => 'manual',
                'providers_component' => 'midgardmvc_core_providers_component_midgardmvc',
                'components' => array
                (
                    'midgardmvc_core' => true,
                ),
            )
        );
        $request = new midgardmvc_core_request();
        $request->add_component_to_chain($this->_core->component->get('midgardmvc_core'));
        $this->_core->context->create($request);
    }
    
    public function tearDown()
    {   
        // Delete the context        
        $this->_core->context->delete();
        
        parent::tearDown();
    }
}
?>
