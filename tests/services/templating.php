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
    public function setUp()
    {
        $_ENV['MIDGARD_ENV_GLOBAL_CACHEDIR'] = '/tmp';
        parent::setUp();
    }

    public function test_get_element_simple()
    {
        $original_element = file_get_contents(MIDGARDMVC_ROOT . "/midgardmvc_core/templates/ROOT.xhtml");
        $element = midgardmvc_core::get_instance()->templating->get_element('ROOT', false);
        $this->assertEquals($original_element, $element, 'Template returned by templating service should be the same as the template file');
    }

    public function test_get_element_include()
    {
        $request = midgardmvc_core_request::get_for_intent('/subdir');
        $routes = midgardmvc_core::get_instance()->component->get_routes($request);
        $request->set_route($routes['page_read']);
        midgardmvc_core::get_instance()->context->create($request);
        
        $original_element = file_get_contents(MIDGARDMVC_ROOT . "/midgardmvc_core/templates/ROOT.xhtml");
        $element = midgardmvc_core::get_instance()->templating->get_element('ROOT');
        $this->assertNotEquals($original_element, $element, 'Template returned by templating service should not be the same as the template file because of includes');

        $this->assertTrue(strpos($element, '<h1 mgd:property="title" tal:content="current_component/object/title">Title</h1>') !== false);

        midgardmvc_core::get_instance()->context->delete();
    }

    /**
     * @expectedException OutOfBoundsException
     */
    public function test_get_element_missing()
    {
        $element = midgardmvc_core::get_instance()->templating->get_element('missing-element', false);
    }

    public function test_dynamic_call()
    {
        $data = midgardmvc_core::get_instance()->templating->dynamic_call('/subdir', 'page_read', array());
        $this->assertTrue(is_array($data), "Test whether dynamic call returned data");
        $this->assertTrue(isset($data['object']), "Test whether route returned an object");
        $this->assertEquals('Subfolder', $data['object']->title);
    }

    /**
     * @expectedException OutOfRangeException
     */
    public function test_dynamic_call_invalid()
    {
        $data = midgardmvc_core::get_instance()->templating->dynamic_call('/subdir', 'missing_route', array());
    }

    public function test_dynamic_load()
    {
        // Test with returned output
        $content = midgardmvc_core::get_instance()->templating->dynamic_load('/subdir', 'page_read', array(), true);
        $this->assertTrue(strpos($content, '<h1 mgd:property="title">Subfolder</h1>') !== false);

        // Test with direct output
        ob_start();
        midgardmvc_core::get_instance()->templating->dynamic_load('/subdir', 'page_read', array());
        $newcontent = ob_get_clean();
        $this->assertEquals($content, $newcontent);
    }
}
