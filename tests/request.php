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

    public function test_methods_valid()
    {
        $verbs = array
        (
            // Safe methods, should not modify data
            'head',
            'get',
            'trace',
            'options',
            // Idempotent methods, multiple identical requests should produce same effect
            'put',
            'delete',
            // Other HTTP methods
            'post',
            'connect',
            'patch',
            // WebDAV methods
            'propfind',
            'proppatch',
            'mkcol',
            'copy',
            'move',
            'lock',
            'unlock',
        );

        $request = new midgardmvc_core_request();
        foreach ($verbs as $verb)
        {
            $request->set_method(strtoupper($verb));
            $this->assertEquals($verb, $request->get_method());
        }
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_method_invalid()
    {
        $request = new midgardmvc_core_request();
        $request->set_method('FOO');
    }

    public function test_cache_identifier()
    {
        $request = midgardmvc_core_request::get_for_intent('/');
        $original = $request->get_identifier();
        $this->assertEquals($original, $request->get_identifier());

        $request = midgardmvc_core_request::get_for_intent('/');
        $this->assertEquals($original, $request->get_identifier(), 'Testing that second request with same environment returns same cache identifier');

        $request = midgardmvc_core_request::get_for_intent('/subdir');
        $subdir = $request->get_identifier();
        $this->assertNotEquals($original, $subdir);

        $route = new midgardmvc_core_route('page_read', '/foo/?bar={$baz}', 'foo', 'bar', array('content' => 'foo'));
        $request->set_route($route);
        $this->assertNotEquals($subdir, $request->get_identifier(), 'Testing that template names affect cache identifier');
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
