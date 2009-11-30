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
        $this->configuration = midgardmvc_core::get_instance()->configuration;
    }
    
    public function action_messages($route_id, &$data, $args)
    {
        if (   !midgardmvc_core::get_instance()->uimessages->supports('comet')
            || !midgardmvc_core::get_instance()->uimessages->can_view())
        {
            return;
        }

        $type = null;
        $name = null;

        if (isset(midgardmvc_core::get_instance()->dispatcher->get["cometType"]))
        {
            $type = midgardmvc_core::get_instance()->dispatcher->get["cometType"];
        }

        if (isset(midgardmvc_core::get_instance()->dispatcher->get["cometName"]))
        {
            $name = midgardmvc_core::get_instance()->dispatcher->get["cometName"];
        }

        if (   $type == null
            && $name == null)
        {
            throw new midgardmvc_exception_notfound("No comet name or type defined");
        }

        if (ob_get_level() == 0)
        {
            ob_start();
        }

        while (true)
        {
            $messages = '';    
            if (midgardmvc_core::get_instance()->uimessages->has_messages())
            {
                $messages = midgardmvc_core::get_instance()->uimessages->render_as('comet');
            }
            else
            {
                midgardmvc_core::get_instance()->uimessages->add(array(
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