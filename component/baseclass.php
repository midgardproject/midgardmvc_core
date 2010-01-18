<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Component interface baseclass for Midgard MVC
 *
 * @package midgardmvc_core
 */
abstract class midgardmvc_core_component_baseclass implements midgardmvc_core_component_interface
{
    /**
     * Legacy access to configuration service. Deprecated, use midgardmvc_core::get_instance()->configuration instead.
     *
     * @var midgardmvc_core_services_configuration
     */
    public $configuration = null;
    
    public function __construct()
    {
        $this->configuration = midgardmvc_core::get_instance()->configuration;
        midgardmvc_core::get_instance()->i18n->set_translation_domain(__CLASS__);
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
