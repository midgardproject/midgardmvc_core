<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */
require_once(midgardmvc_core::get_component_path('midgardmvc_core') . '/phptal_include.php');

/**
 * Gettext localization service for Midgard MVC
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_services_i18n_gettext implements midgardmvc_core_services_i18n
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
        try 
        {
            // set language to use for this session (first valid language will 
            // be used)
            $this->language = midgardmvc_core::get_instance()->configuration->get('default_language');
        }
        catch (Exception $e)
        {
            echo $e;
        }
    }

    /**
     * Gets a string from the i18n database
     * If substitutes given then it will return the string with substitued elements
     *
     * @param string $key the msgid to lookup
     * @param component mvc component
     * @param array $subs associative array with $name => $value pairs
     *
     * @return string the translated string
     */
    public function get($key, $component = null, $subs = array())
    {
        if (is_null($component))
        {
            $msgstr = gettext($key);
        }
        else
        {
            $msgstr = dgettext($component, $key);
        }

        if (!count($subs))
        {
            // no substitutions, return the string
            return $msgstr;
        }

        if ($msgstr)
        {
            foreach ($subs as $name => $value )
            {
                // lookup the name prefixed with $ sign in msgstr and if replace it if found
                $msgstr = str_replace('${' . $name . '}', $value, $msgstr);
            }
        }

        return $msgstr;
    }
    
    public function &set_translation_domain($component_name)
    {
        // If no component name is set, then it's from the core
        // translations are going to get searched.
        if (!$component_name)
        {
            $component_name = 'midgardmvc_core';
        }
        
         // Checking if TAL translator is already available
        if ( isset($this->tr[$component_name])) 
        { 
            // useDomain must be called. Otherwise gettext context is not changed 
            $this->tr[$component_name]->useDomain($component_name); 
            return $this->tr[$component_name]; 
        } 

        try
        {
            $this->tr[$component_name] = new PHPTAL_GetTextTranslator();
            $this->tr[$component_name]->setLanguage($this->language.'.utf8', $this->language);
        }
        catch (Exception $e)
        {
            echo ($e);
        }
        // register gettext domain to use
        $path = midgardmvc_core::get_component_path($component_name) . '/locale/';
        $this->tr[$component_name]->addDomain($component_name, $path);

        // specify current domain
        $this->tr[$component_name]->useDomain($component_name);
        return $this->tr[$component_name]; 
    }
    
    //public function set_language(midgard_language $language, $switch_content_language)
    public function set_language($locale, $switch_content_language)
    {
        $this->language = $locale;
        
        foreach($this->tr as $key => $val)
        {
            $this->tr[$key]->setLanguage($this->language.'.utf8', $this->language);
        }

        // midgardmvc_core::get_instance()->context->gettext_translator->setLanguage($lang.'.utf8', $lang);

        if ($switch_content_language)
        {
            $this->set_content_language($language);
        }
    }
    
    public function set_content_language(midgard_language $language)
    {
        die("Not implemented yet");
    }

    public function get_language()
    {
        return $this->language;
    }
    
}
?>
