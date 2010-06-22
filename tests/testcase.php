<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

if (! defined('MIDGARDMVC_TEST_RUN'))
{
    define('MIDGARDMVC_TEST_RUN', true);
}

if (! defined('MIDGARD_CONFIG'))
{
    define('MIDGARD_CONFIG', 'midgard');
}
if (! defined('MIDGARDMVC_TESTS_LOGLEVEL'))
{
    define('MIDGARDMVC_TESTS_LOGLEVEL', 'info');
}
if (! defined('MIDGARDMVC_TESTS_SITEGROUP'))
{
    define('MIDGARDMVC_TESTS_SITEGROUP', 1);
}
if (! defined('MIDGARDMVC_TESTS_ENABLE_OUTPUT'))
{
    define('MIDGARDMVC_TESTS_ENABLE_OUTPUT', false);
}

if (! defined('COMPONENT_DIR'))
{
    define('COMPONENT_DIR', realpath(dirname(__FILE__) . '/../../'));
}

require_once('PHPUnit/Framework.php');

require_once(dirname(__FILE__) . '/midgard.php');

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

        // Load MidCOM framework for tests
        require_once(COMPONENT_DIR . '/midgardmvc_core/framework.php');
        $this->_core = midgardmvc_core::get_instance('manual');
        $this->_core->context->create();
        $this->_core->componentloader = new midgardmvc_core_component_loader();
    }
    
    public function tearDown()
    {   
        // Delete the context        
        $this->_core->context->delete();
        
        parent::tearDown();
    }
}
?>
