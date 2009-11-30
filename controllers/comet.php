<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Comet listeners controller
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_controllers_comet
{
    public function __construct(midgardmvc_core_component_interface $instance)
    {
        $this->configuration = $_MIDCOM->configuration;
    }
    
    public function action_messages($route_id, &$data, $args)
    {
        if (   !$_MIDCOM->uimessages->supports('comet')
            || !$_MIDCOM->uimessages->can_view())
        {
            return;
        }

        $type = null;
        $name = null;

        if (isset($_MIDCOM->dispatcher->get["cometType"]))
        {
            $type = $_MIDCOM->dispatcher->get["cometType"];
        }

        if (isset($_MIDCOM->dispatcher->get["cometName"]))
        {
            $name = $_MIDCOM->dispatcher->get["cometName"];
        }

        if (   $type == null
            && $name == null)
        {
            throw new midcom_exception_notfound("No comet name or type defined");
        }

        if (ob_get_level() == 0)
        {
            ob_start();
        }

        while (true)
        {
            $messages = '';    
            if ($_MIDCOM->uimessages->has_messages())
            {
                $messages = $_MIDCOM->uimessages->render_as('comet');
            }
            else
            {
                $_MIDCOM->uimessages->add(array(
                    'title' => 'Otsikko from comet',
                    'message' => 'viesti from comet...'
                ));
            }
            
            midgardmvc_core_helpers_comet::pushdata($messages, $type, $name);

            ob_flush();
            flush();
            sleep(5);
        }

        // $data['messages'] = $messages;
    }

}
?>