<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Unit tests for the request class
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_tests_request extends midgardmvc_core_tests_testcase
{
    public function test_resolve_node_root()
    {
        $request = new midgardmvc_core_request();
        $request->resolve_node('/');
        $this->assertEquals('/', $request->get_path());
    }

    public function test_resolve_node_subdir()
    {
        $request = new midgardmvc_core_request();
        $request->resolve_node('/subdir');
        $this->assertEquals('/subdir/', $request->get_path());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_intent_nointent()
    {
        $newreq = midgardmvc_core_request::get_for_intent(null);
    }

    public function test_intent_request()
    {
        $request = new midgardmvc_core_request();
        $request->resolve_node('/subdir');

        $newreq = midgardmvc_core_request::get_for_intent($request);
        $this->assertEquals($request->get_path(), $newreq->get_path());
    }

    public function test_intent_node()
    {
        $node = midgardmvc_core::get_instance()->hierarchy->get_node_by_path('/subdir');
        $newreq = midgardmvc_core_request::get_for_intent($node);
        $this->assertEquals('/subdir/', $newreq->get_path());
    }

    public function test_intent_component()
    {
        $newreq = midgardmvc_core_request::get_for_intent('midgardmvc_core');
        $this->assertEquals('/', $newreq->get_path());
    }

    public function test_intent_path()
    {
        $newreq = midgardmvc_core_request::get_for_intent('/subdir');
        $this->assertEquals('/subdir/', $newreq->get_path());
    }
}
?>
