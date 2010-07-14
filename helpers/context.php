<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Context data management helper for Midgard MVC
 *
 * This is the legacy way to access request data. Use the direct request object instead.
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_helpers_context
{
    private $request = null;

    public function __construct()
    {
    }

    /**
     * Create and prepare a new component context.
     */
    public function create(midgardmvc_core_helpers_request $request = null)
    {
        if (!is_null($request))
        {
            $this->request = $request;
        }
    }
    
    /**
     * Remove a context and return to previous.
     */
    public function delete()
    {
        return;
    }

    public function register_delete_callback($callback)
    {
        return;
    }
    
    public function get_current_context()
    {
        return 0;
    }
    
    /**
     * Get a reference of the context data array
     *
     * @param int $context_id ID of the current context
     * @return array Context data
     */
    public function get($context_id = null)
    {
        return $this->request->get_data();
    }

    /**
     * Get value of a particular context data array item
     *
     * @param string $key Key to get data of
     * @param int $context_id ID of the current context
     * @return array Context data
     */
    public function get_item($key, $context_id = null)
    {
        $mvc = midgardmvc_core::get_instance();
        $mvc->log('Midgard MVC', "Accessing request data via legacy context key {$key}", 'info');
        if (is_null($this->request))
        {
            throw new Exception('No request set for context');
        }
        return $this->request->get_data_item($key);
    }

    /**
     * Set value of a particular context data array item
     *
     * @param string $key Key to set data of
     * @param mixed $value Value to set to the context data array
     * @param int $context_id ID of the current context
     */
    public function set_item($key, $value, $context_id = null)
    {
        $mvc = midgardmvc_core::get_instance();
        $mvc->log('Midgard MVC', "Setting request data via legacy context key {$key}", 'info');
        if (is_null($this->request))
        {
            throw new Exception('No request set for context');
        }
        return $this->request->set_data_item($key, $value);
    }

    /**
     * Get value of current context data array item
     *
     * @param string $key Key to get data of
     * @return mixed Value
     **/
    public function __get($key)
    {
        return $this->get_item($key);
    }

    /**
     * Set value of a particular context data array item
     *
     * @param string $key Key to set data to
     * @param mixed $value Value to set
     */
    public function __set($key, $value)
    {
        $this->set_item($key, $value);
    }

    /**
     * Check if data array item exists in current context 
     *
     * @param string $key Key to check for
     * @return bool
     **/
    public function __isset($key)
    {
        return $this->request->isset_data_item($key);
    }
}
?>
