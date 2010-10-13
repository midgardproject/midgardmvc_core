<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Unit tests for the exception handler class
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_tests_exceptionhandler extends midgardmvc_core_tests_testcase
{
    public function test_handle()
    {
        $exceptionhandler = new midgardmvc_core_exceptionhandler();
        try
        {
            $data = midgardmvc_core::get_instance()->templating->dynamic_call('/subdir', 'missing_route', array());
        }
        catch (Exception $e)
        {
            ob_start();
            $exceptionhandler->handle($e);
            $errorpage = ob_get_clean();
            $this->assertTrue(strpos($errorpage, '<body class="OutOfRangeException">') !== false);
        }
    }
}
?>
