<?php
/**
 * @package midcom_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */
include MIDGARDMVC_ROOT . "/midcom_core/services/uimessages.php";

/**
 * Basic UI Message class
 *
 * @package midcom_core
 */
class midcom_core_services_uimessages_simple extends midcom_core_services_uimessages_baseclass implements midcom_core_services_uimessages
{
    /**
     * The current message stack
     *
     * @var Array
     * @access private
     */
    private $message_stack = array();

    /**
     * List of messages retrieved from session to avoid storing them again
     *
     * @var Array
     * @access private
     */
    private $messages_from_session = array();

    /**
     * ID of the latest UI message added so we can auto-increment
     *
     * @var integer
     * @access private
     */
    private $latest_message_id = 0;

    /**
     * List of allowed message types
     *
     * @var Array
     * @access private
     */
    private $allowed_types = array();
    
    public function __construct(&$configuration = array())
    {
        // Set the list of allowed message types
        $this->allowed_types[] = 'info';
        $this->allowed_types[] = 'ok';
        $this->allowed_types[] = 'warning';
        $this->allowed_types[] = 'error';
        $this->allowed_types[] = 'debug';
        
        $this->get_messages();
    }
    
    private function get_messages()
    {
        // Read messages from session
        $session = new midcom_core_services_sessioning('midcom_services_uimessages');
        if ($session->exists('midcom_services_uimessages_stack'))
        {
            // We've got old messages in the session
            $stored_messages = $session->get('midcom_services_uimessages_stack');
            $session->remove('midcom_services_uimessages_stack');
            if (! is_array($stored_messages))
            {
                return false;
            }

            foreach ($stored_messages as $message)
            {                
                $id = $this->add($message);
                $this->messages_from_session[] = $id;
            }
        }

        return $this->messages_from_session;
    }
    
    public function has_messages()
    {
        if (count($this->message_stack) > 0)
        {
            return true;
        }
        
        return false;
    }
    
    /**
     * Store unshown UI messages from the stack to user session.
     */
    public function store()
    {
        if (count($this->message_stack) == 0)
        {
            // No unshown messages
            return true;
        }

        // We have to be careful what messages to store to session to prevent them
        // from accumulating
        $messages_to_store = array();
        foreach ($this->message_stack as $id => $message)
        {
            // Check that the messages were not coming from earlier session
            if (! in_array($id, $this->messages_from_session))
            {
                $messages_to_store[$id] = $message;
            }
        }
        if (count($messages_to_store) == 0)
        {
            // We have only messages coming from earlier sessions, and we ditch those
            return true;
        }

        $session = new midcom_core_services_sessioning('midcom_services_uimessages');

        // Check if some other request has added stuff to session as well
        if ($session->exists('midcom_services_uimessages_stack'))
        {
            $old_stack = $session->get('midcom_services_uimessages_stack');
            $messages_to_store = array_merge($old_stack, $messages_to_store);
        }
        $session->set('midcom_services_uimessages_stack', $messages_to_store);
        $this->message_stack = array();
    }
    
    /**
     * Add a message to be shown to the user.
     * @param array $data Message parts
     */
    public function add($data)
    {        
        if (   !array_key_exists('title', $data)
            || !array_key_exists('message', $data))
        {
            return false;
        }
        
        if (! array_key_exists('type', $data))
        {
            $data['type'] = 'info';
        }
        
        // Make sure the given class is allowed
        if (! in_array($data['type'], $this->allowed_types))
        {
            // Message class not in allowed list
            return false;
        }

        // Normalize the title and message contents
        $title = str_replace("'", '"', $data['title']);
        $message = str_replace("'", '"', $data['message']);

        $this->latest_message_id++;

        // Append to message stack
        $this->message_stack[$this->latest_message_id] = array
        (
            'title'   => $title,
            'message' => $message,
            'type'    => $data['type'],
        );
        
        return $this->latest_message_id;
    }
    
    public function remove($key)
    {
        if (array_key_exists($key, $this->message_stack))
        {
            unset($this->message_stack[$key]);
        }
    }
    
    public function get($key)
    {
        if (array_key_exists($key, $this->message_stack))
        {
            return $this->message_stack[$key];
        }
    }
    
    public function render($key=null)
    {
        $html = '';

        if (count($this->message_stack) > 0)
        {
            $html .= "<ul class=\"midcom_services_uimessages\">\n";
            
            if (   !is_null($key)
                && array_key_exists($key, $this->message_stack))
            {
                $html .= $this->render_message_html($this->message_stack[$key]);

                // Remove the message from stack
                unset($this->message_stack[$key]);
            }
            else
            {
                foreach ($this->message_stack as $id => $message)
                {
                    $html .= $this->render_message_html($message);

                    // Remove the message from stack
                    unset($this->message_stack[$id]);
                }                
            }

            $html .= "</ul>\n";
        }
        
        return $html;
    }
    
    /**
     * Render single message to HTML
     */
    private function render_message_html($message)
    {
        $html  = "<li class=\"midcom_services_uimessages_message msu_{$message['type']}\">\n";
        $html .= "    <div class=\"midcom_services_uimessages_message_title\">{$message['title']}</div>\n";
        $html .= "    <div class=\"midcom_services_uimessages_message_msg\">{$message['message']}</div>\n";
        $html .= "</li>\n";
        
        return $html;
    }
}
?>
