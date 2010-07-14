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
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_helpers_context
{
    private $contexts = array();
    private $current_context = 0;
    private $delete_callbacks = array();
    
    public function __construct()
    {
    }

    /**
     * Create and prepare a new component context.
     */
    public function create()
    {
        $context_id = count($this->contexts);
        $this->contexts[$context_id] = array
        (
            // TODO: Convert to 'application/xhtml+xml' as soon as Midgard MVC javascripts are compatible with it
            'mimetype'             => 'text/html', 
            'template_engine'      => 'tal',
            'template_entry_point' => 'ROOT',
            'content_entry_point'  => 'content',
            'component'            => 'midgardmvc_core',
        );
        
        if ($context_id > 0)
        {
            // Creating a new request context, copy some values from ctx 0
            if (isset($this->contexts[0]['root']))
            {
                $this->contexts[$context_id]['root'] = $this->contexts[0]['root'];
            }
            if (isset($this->contexts[0]['root_node']))
            {
                $this->contexts[$context_id]['root_node'] = $this->contexts[0]['root_node'];
            }
            if (isset($this->contexts[0]['cache_enabled']))
            {
                $this->contexts[$context_id]['cache_enabled'] = $this->contexts[0]['cache_enabled'];
            }
        }
        
        $this->current_context = $context_id;
    }
    
    /**
     * Remove a context and return to previous.
     */
    public function delete()
    {
        foreach ($this->delete_callbacks as $callback)
        {
            // Notify callbacks that the context has been removed
            call_user_func($callback, $this->current_context);
        }
        
        if ($this->current_context == 0)
        {
            $this->contexts = array();
            return;
        }
        
        $old_context = $this->current_context;
        $this->current_context--;
        
        unset($this->contexts[$old_context]);
    }

    public function register_delete_callback($callback)
    {
        if (!is_callable($callback))
        {
            throw new InvalidArgumentException("Context deletion callback is not callable");
        }

        $this->delete_callbacks[] = $callback;
    }
    
    public function get_current_context()
    {
        return $this->current_context;
    }
    
    /**
     * Get a reference of the context data array
     *
     * @param int $context_id ID of the current context
     * @return array Context data
     */
    public function get($context_id = null)
    {
        if (is_null($context_id))
        {
            $context_id = $this->current_context;
        }

        if (!isset($this->contexts[$context_id]))
        {
            throw new OutOfBoundsException("Midgard MVC context {$context_id} not found.");
        }
        
        return $this->contexts[$context_id];
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
        if (is_null($context_id))
        {
            $context_id = $this->current_context;
        }
        
        if (!isset($this->contexts[$context_id]))
        {
            throw new OutOfBoundsException("Midgard MVC context {$context_id} not found.");
        }
        
        if (!isset($this->contexts[$context_id][$key]))
        {
            throw new OutOfBoundsException("Midgard MVC context key '{$key}' in context {$context_id} not found.");
        }
        
        return $this->contexts[$context_id][$key];
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
        if (is_null($context_id))
        {
            $context_id = $this->current_context;
        }
        
        if (!isset($this->contexts[$context_id]))
        {
            throw new OutOfBoundsException("Midgard MVC context {$context_id} not found.");
        }
        
        $this->contexts[$context_id][$key] = $value;
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
        if (   isset($this->contexts[$this->current_context])
            && isset($this->contexts[$this->current_context][$key]))
        {
            return true;
        }

        return false;
    }
}
?>
