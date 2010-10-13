<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

 /**
  * UI Message interface
  *
  * @package midgardmvc_core
  */
interface midgardmvc_core_services_uimessages
{
    /**
     * @param &$configuration Configuration for the current uimessage type
     */
    public function __construct(&$configuration = array());
    
    public function add(array $data);
    
    public function remove($key);
    
    public function get($key);
    
    public function store();
    
    public function has_messages();
    
    public function render($key = null);
    
    public function render_as($type = 'comet', $key = null);

    public function supports($type = 'comet');
}

/**
 * UI Messages core class
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_services_uimessages_baseclass
{
    public $implementation = null;
    private $configuration = array();
    
    public function __construct()
    {
        $this->load_configuration();
        
        $classname = null;
        $this->implementation =& midgardmvc_core::get_instance()->uimessages;
    }
    
    private function load_configuration()
    {
        $this->configuration = midgardmvc_core::get_instance()->configuration->get('uimessages_configuration');
        if (! is_array($this->configuration))
        {
            $this->configuration = array();
        }
    }
    
    public function add($data)
    {
        if (!$this->implementation)
        {
            return false;
        }
        return $this->implementation->add($data);
    }
    
    public function store()
    {
        if (!$this->implementation)
        {
            return false;
        }
        return $this->implementation->store();
    }
    
    public function has_messages()
    {
        if (!$this->implementation)
        {
            return false;
        }
        return $this->implementation->has_messages();
    }

    public function can_view($user=null)
    {
        if (midgardmvc_core::get_instance()->context->mimetype === 'text/html')
        {
            return true;            
        }
        
        return false;
    }

    public function render($key = null)
    {
        if (!$this->implementation)
        {
            return;
        }
        return $this->implementation->render($key);
    }
    
    public function render_as($type = 'comet', $key = null)
    {
        if ($this->supports($type))
        {
            return $this->implementation->render_as($type, $key);
        }
        
        return false;
    }
    
    public function supports($type = 'comet')
    {
        if (!$this->implementation)
        {
            return false;
        }
        if ($this->implementation->supports($type))
        {
            return true;
        }
        
        return false;
    }
}

?>
