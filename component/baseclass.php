<?php
/**
 * @package midcom_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Component interface definition for MidCOM 3
 *
 * The defines the structure of component instance interface class
 *
 * @package midcom_core
 */
abstract class midcom_core_component_baseclass implements midcom_core_component_interface
{
    /**
     * @var midcom_core_services_configuration
     */
    public $configuration = false;

    /**
     * @var midgard_page
     */
    public $folder = null;
    
    public function __construct(midcom_core_services_configuration $configuration, midgard_page $folder = null)
    {        
        $this->configuration = $configuration;
        $this->folder = $folder;
        $component = $configuration->get_component();
        midcom_core_midcom::get_instance()->i18n->set_translation_domain($component);
    }

    public function initialize()
    {
        $this->on_initialize();
    }

    public function on_initialize()
    {
    }
}
?>
