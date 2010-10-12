<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Tests for the templating service
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_tests_services_templating extends midgardmvc_core_tests_testcase
{
    public function test_dynamic_call()
    {
        $data = midgardmvc_core::get_instance()->templating->dynamic_call('/subdir', 'page_read', array());
        $this->assertTrue(is_array($data), "Test whether dynamic call returned data");
        $this->assertTrue(isset($data['object']), "Test whether route returned an object");
        $this->assertEquals('Subfolder', $data['object']->title);
    }
}
