<?php
/**
 * @package midcom_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

 /**
  * Localization interface
  *
  * @package midcom_core
  */
interface midcom_core_services_i18n
{
    /**
     * @param &$configuration  Configuration for the current localization type
     */
    public function __construct();
    
//    public function set_language(midgard_language $language, $switch_content_language);

    public function set_language($locale, $switch_content_language);
    
    public function set_content_language(midgard_language $language);
}
?>