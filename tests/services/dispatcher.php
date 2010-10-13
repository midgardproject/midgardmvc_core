<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Tests for the dispatcher
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_tests_services_dispatcher extends midgardmvc_core_tests_testcase
{
    public function test_generate_url()
    {
        $url = midgardmvc_core::get_instance()->dispatcher->generate_url('page_read', array(), 'midgardmvc_core');
        $this->assertEquals('/', $url);
    }
}
