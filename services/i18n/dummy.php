<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Dummy localization service for Midgard MVC
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_services_i18n_dummy implements midgardmvc_core_services_i18n
{
    /**
     * Simple constructor.
     * 
     * @access public
     */
     
    private $tr = array();
    private $language = null;
     
    public function __construct()
    {
    }
    
    public function get($key, $component = null)
    {
        return $key;
    }
    
    public function set_translation_domain($component_name)
    {
        return null;
    }
    
    //public function set_language(midgard_language $language, $switch_content_language)
    public function set_language($locale, $switch_content_language)
    {
    }
    
    public function set_content_language(midgard_language $language)
    {
        die("Not implemented yet");
    }
}
