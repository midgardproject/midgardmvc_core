<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Tests for the UI message service
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_tests_services_uimessagesTest extends midgardmvc_core_tests_testcase
{
    public function test_has_remove()
    {
        $this->assertFalse(midgardmvc_core::get_instance()->uimessages->has_messages());

        $message = array
        (
            'title' => 'Foo',
            'message' => 'Bar',
            'type' => 'ok',
        );
        $id = midgardmvc_core::get_instance()->uimessages->add($message);

        $this->assertTrue(midgardmvc_core::get_instance()->uimessages->has_messages());

        midgardmvc_core::get_instance()->uimessages->remove($id);
        $this->assertFalse(midgardmvc_core::get_instance()->uimessages->has_messages());
    }

    public function test_add()
    {
        $stat = midgardmvc_core::get_instance()->uimessages->add(array('foo' => 'bar'));
        $this->assertFalse($stat);

        $stat = midgardmvc_core::get_instance()->uimessages->add(array('title' => 'foo', 'type' => 'ok'));
        $this->assertFalse($stat);

        $stat = midgardmvc_core::get_instance()->uimessages->add(array('title' => 'foo', 'message' => 'bar', 'type' => 'fubar'));
        $this->assertFalse($stat);

        $stat = midgardmvc_core::get_instance()->uimessages->add(array('title' => 'foo', 'message' => 'bar'));
        $this->assertNotEquals(false, $stat);
        midgardmvc_core::get_instance()->uimessages->remove($stat);
    }

    public function test_get()
    {
        $message = array
        (
            'title' => 'Foo',
            'message' => 'Bar',
            'type' => 'ok',
        );
        $id = midgardmvc_core::get_instance()->uimessages->add($message);
        $storedmessage = midgardmvc_core::get_instance()->uimessages->get($id);
        $this->assertEquals($message, $storedmessage);
        midgardmvc_core::get_instance()->uimessages->remove($id);
    }

    public function test_render()
    {
        $message = array
        (
            'title' => 'Foo',
            'message' => 'Bar',
            'type' => 'ok',
        );
        $id = midgardmvc_core::get_instance()->uimessages->add($message);
        $content = midgardmvc_core::get_instance()->uimessages->render();
        $this->assertTrue(strpos($content, '<div class="midgardmvc_services_uimessages_message_title">Foo</div>') !== false);

        $this->assertEquals(null, midgardmvc_core::get_instance()->uimessages->get($id), 'Ensure the message was removed after rendering');
    }

    public function test_render_key()
    {
        $message = array
        (
            'title' => 'Foo',
            'message' => 'Bar',
            'type' => 'ok',
        );
        $id = midgardmvc_core::get_instance()->uimessages->add($message);
        $content = midgardmvc_core::get_instance()->uimessages->render($id);
        $this->assertTrue(strpos($content, '<div class="midgardmvc_services_uimessages_message_title">Foo</div>') !== false);

        $this->assertEquals(null, midgardmvc_core::get_instance()->uimessages->get($id), 'Ensure the message was removed after rendering');
    }
}
