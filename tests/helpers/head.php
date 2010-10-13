<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Test to see if the head helper is working
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_tests_helpers_head extends midgardmvc_core_tests_testcase
{
    public function test_jquery()
    {
        $head = new midgardmvc_core_helpers_head();
        $this->assertTrue($head->jquery_enabled, 'Ensure that jQuery is enabled by default');
        ob_start();
        $head->print_elements();
        $headers = ob_get_clean();
        $this->assertTrue(strpos($headers, '<script type="text/javascript" src="/midgardmvc-static/midgardmvc_core/jQuery/jquery-1.4.2.min.js"></script>') !== false, 'Check for jQuery script tag');

        $stat = $head->enable_jquery();
        $this->assertEquals(null, $stat, 'Ensure that jQuery is enabled only once');
    }

    public function test_jquery_state()
    {
        $head = new midgardmvc_core_helpers_head();
        $head->add_jquery_state_script('alert("foo");');
        ob_start();
        $head->print_elements();
        $headers = ob_get_clean();
        $this->assertTrue(strpos($headers, "jQuery(document).ready(function() {\n\nalert(\"foo\");") !== false, 'Check for jQuery state script');

        // Add another and see that both are included
        $head->add_jquery_state_script('alert("bar");');
        ob_start();
        $head->print_elements();
        $headers = ob_get_clean();
        $this->assertTrue(strpos($headers, "jQuery(document).ready(function() {\n\nalert(\"foo\");\n\nalert(\"bar\");") !== false, 'Check for jQuery state script');
    }

    public function test_jsfile()
    {
        $head = new midgardmvc_core_helpers_head();
        $head->add_jsfile('http://example.net/foo.js');
        ob_start();
        $head->print_elements();
        $headers = ob_get_clean();
        $this->assertTrue(strpos($headers, '<script type="text/javascript" src="http://example.net/foo.js"></script>') !== false, 'Check for required script tag');
    }

    public function test_script()
    {
        $head = new midgardmvc_core_helpers_head();
        $head->add_script('alert("foo");');
        ob_start();
        $head->print_elements();
        $headers = ob_get_clean();
        $this->assertTrue(strpos($headers, "<script type=\"text/javascript\">\n        alert(\"foo\");") !== false, 'Check for required script element');
    }

    public function test_meta()
    {
        $head = new midgardmvc_core_helpers_head();

        $stat = $head->add_meta(array('foo' => 'bar'));
        $this->assertFalse($stat, 'Ensure that setting invalid meta elements fails');

        $meta = array
        (
            'name' => 'keywords',
            'content' => 'midgardmvc',
        );
        $head->add_meta($meta);
        ob_start();
        $head->print_elements();
        $headers = ob_get_clean();
        $this->assertTrue(strpos($headers, '<meta name="keywords"  content="midgardmvc" />') !== false, 'Check for required meta element');
    }

    public function test_link()
    {
        $head = new midgardmvc_core_helpers_head();

        $stat = $head->add_link(array('foo' => 'bar'));
        $this->assertFalse($stat, 'Ensure that setting invalid link elements fails');

        $meta = array
        (
            'href' => 'http://example.net/',
            'rel' => 'alternate',
        );
        $head->add_link($meta);

        $stat = $head->add_link($meta);
        $this->assertFalse($stat, 'Ensure that same URL gets added only once');

        $conditional = array
        (
            'href' => 'http://example.net/ie.css',
            'rel' => 'stylesheet',
            'condition' => 'IE6',
        );
        $head->add_link($conditional);

        ob_start();
        $head->print_elements();
        $headers = ob_get_clean();
        $this->assertTrue(strpos($headers, '<link href="http://example.net/"  rel="alternate" />') !== false, 'Check for required meta element');
        $this->assertTrue(strpos($headers, "<!--[if IE6]>\n        <link href=\"http://example.net/ie.css\"  rel=\"stylesheet\" />") !== false, 'Check for conditional meta element');
    }
}
?>
