<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

class midgardmvc_core_services_loader
{
    private $services = array();
    
    public function __construct()
    {
        
    }
    
    public function &load($name, &$configuration=null)
    {        
        $services_implementation = midgardmvc_core_midcom::get_instance()->configuration->get("services_{$name}");
        if (   $services_implementation
            && !array_key_exists($name, $this->services))
        {
            if (! is_null($configuration))
            {
                $this->services[$name] = new $services_implementation($configuration);
            }
            else
            {
                $this->services[$name] = new $services_implementation();
            }
        }
        
        if (array_key_exists($name, $this->services))
        {
            return $this->services[$name];
        }
        else
        {
            throw new Exception("Couldn't load service {$name}. Please check your configuration!");
        }
    }
}

?>