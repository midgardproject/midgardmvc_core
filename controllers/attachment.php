<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Very simple attachment serving by guid.
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_controllers_attachment
{

    public function __construct(midgardmvc_core_component_interface $instance)
    {
      $this->configuration = $instance->configuration;
    }
    
    /**
     * Function serves the attachment by provided guid and exits.
     * @todo: Permission handling
     * @todo: Direct filesystem serving
     * @todo: Configuration options
     */
    public function get_serve(array $args)
    {
        $att = new midgard_attachment($args['guid']);
                
        if (midgardmvc_core::get_instance()->configuration->enable_attachment_cache)
        {
            midgardmvc_core::get_instance()->dispatcher->header('Location: ' . midgardmvc_core_helpers_attachment::get_url($att));
            midgardmvc_core::get_instance()->dispatcher->end_request();
        }

        $blob = new midgard_blob($att);
        
        midgardmvc_core::get_instance()->dispatcher->header('Content-type: '.$att->mimetype);
        /**
          * If X-Sendfile support is enabled just sending correct headers
          */
        if (midgardmvc_core::get_instance()->configuration->enable_xsendfile)
        {
            midgardmvc_core::get_instance()->dispatcher->header('X-Sendfile: ' . $blob->get_path());
        }
        else
        {
            echo $blob->read_content();
        }
        midgardmvc_core::get_instance()->dispatcher->end_request();
    }
}
?>
