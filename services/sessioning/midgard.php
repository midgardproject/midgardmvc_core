<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Base singleton class of the Midgard MVC sessioning service.
 *
 * This is a singleton class, that is accessible through the Midgard MVC Service
 * infrastructure. It manages session data of Midgard MVC driven applications.
 *
 * This sessioning interface will always work with copies, never with references
 * to work around a couple of bugs mentioned in the details below.
 *
 * This class provides a generic interface to store keyed session values in the
 * domain of the corresponding component.
 *
 * All requests involving this service will always be flagged as no_cache.
 *
 * If you store class instances within a session, which is perfectly safe in
 * general, there are known problems due to the fact, that a class declaration
 * has to be available before it can be deserialized. As PHP sessioning does this
 * deserialization automatically, this might fail with Midgard MVC, where the sequence
 * in which the code gets loaded and the sessioning gets started up is actually
 * undefined. To get around this problems, the sessioning system stores not the
 * actual data in the sessioning array, but a serialized string of the data, which
 * can always be deserialized on PHP sessioning startup (its a string after all).
 * This has an important implication though: The sessioning system always stores
 * copies of the data, not references. So if you put something in to the session
 * store and modify it afterwards, this change will not be reflected in the
 * sessioning store.
 *
 * It will try to be as graceful as possible when starting up the sessioning. Note,
 * that side-effects that might occur together with NemeinAuth are not fully
 * investigated yet.
 *
 * <b>Important:</b>
 *
 * Do <b>never</b> create an instance of this class directly. This is handled
 * by the framework. Instead use midcocm_service_session which ensures the
 * singleton pattern.
 *
 * Do <b>never</b> work directly with the $_SESSION["midgardmvc_session_data"]
 * variable, this is a 100% must-not, as this will break functionality.
 *
 * @package midgardmvc_core
 * @see midgardmvc_core_services_sessioning
 */
class midgardmvc_core_services_sessioning_midgard
{
    const ROOT = 'midgardmvc_session_data';

    private $enabled = true;
    private $dispatcher = null;
    private $data = array();

    private static $started = false;

    /**
     * The constructor will initialize the sessioning, set the output nocacheable
     * and initialize the session data. This might involve creating an empty
     * session array.
     */
    public function __construct()
    {
        $this->start();
    }

    private function start()
    {
        if (self::$started)
        {
            return;
        }

        self::$started = true;
        $this->dispatcher = midgardmvc_core::get_instance()->dispatcher;

        try
        {
            if (!$this->dispatcher->headers_sent())
            {
                $this->dispatcher->session_start();
            }
        }
        catch (Exception $e)
        {
            midgardmvc_core::get_instance()->log(__CLASS__, "Couldn't start session: ".$e->getMessage(), 'warning');
            $this->enabled = false;
            return;
        }


        /* Cache disabling made conditional based on domain/key existence */

        // Check for session data and load or initialize it, if necessary
        if ($this->dispatcher->session_has_var(self::ROOT))
        {
            $this->data = $this->dispatcher->session_get_var(self::ROOT);
        }
        else
        {
            $this->data['midgardmvc_core_services_sessioning'] = array();
            $this->data['midgardmvc_core_services_sessioning']['startup'] = array();

            $this->dispatcher->session_set_var(self::ROOT, $this->data);
        }
    }

    /**
     * Checks, if the specified key has been added to the session store.
     *
     * This is often used in conjunction with get to verify a keys existence.
     *
     * @param string $domain    The domain in which to search for the key.
     * @param mixed $key        The key to query.
     * @return boolean                Indicating availability.
     */
    public function exists($domain, $key)
    {
        if (!$this->enabled)
        {
            return false;
        }
        
        if (! array_key_exists($domain, $this->data))
        {
            // debug_push_class(__CLASS__, __FUNCTION__);
            // debug_add("Request for the domain [{$domain}] failed, because the domain doesn't exist.");
            // debug_pop();
            return false;
        }

        if (! array_key_exists($key, $this->data[$domain]))
        {
            // debug_push_class(__CLASS__, __FUNCTION__);
            // debug_add("Request for the key [{$key}] in the domain [{$domain}] failed, because the key doesn't exist.");
            // debug_pop();
            return false;
        }

        return true;
    }

    /**
     * This is a small, internal helper function, which will load, unserialize and
     * return a given key's value. It is shared by get and remove.
     *
     * @param string $domain    The domain in which to search for the key.
     * @param mixed $key        The key to query.
     * @return mixed            The session key's data value, or NULL on failure.
     */
    private function get_helper($domain, $key)
    {
        if (!$this->enabled)
        {
            return null;
        }
        
        return unserialize($this->data[$domain][$key]);
    }

    /**
     * Returns a value from the session.
     *
     * Returns null if the key
     * is non-existent. Note, that this is not necessarily a valid non-existence
     * check, as the sessioning system does allow null values. Use the exists function
     * if unsure.
     *
     * @param string $domain    The domain in which to search for the key.
     * @param mixed $key        The key to query.
     * @return mixed            The session key's data value, or NULL on failure.
     * @see midgardmvc_core_services_sessioning_midgard::exists()
     */
    public function get($domain, $key)
    {
        if (!$this->enabled)
        {
            return null;
        }
    
        static $no_cache = false;
        if (!$this->exists($domain, $key))
        {
            return null;
        }
        
        if (! $no_cache)
        {
            // midgardmvc_core::get_instance()->cache->content->no_cache();
            $no_cache = true;
        }
        
        return $this->get_helper($domain, $key);
    }

    /**
     * Removes the value associated with the specified key. Returns null if the key
     * is non-existent or the value of the key just removed otherwise. Note, that
     * this is not necessarily a valid non-existence check, as the sessioning
     * system does allow null values. Use the exists function if unsure.
     *
     * @param string $domain    The domain in which to search for the key.
     * @param mixed $key        The key to remove.
     * @return mixed            The session key's data value, or NULL on failure.
     * @see midgardmvc_core_services_sessioning_midgard::exists()
     */
    public function remove($domain, $key)
    {
        if (!$this->enabled)
        {
            return null;
        }

        if ($this->exists($domain, $key))
        {
            $data = $this->get_helper($domain, $key);

            unset($this->data[$domain][$key]);
            $this->dispatcher->session_set_var(self::ROOT, $this->data);

            return $data;
        }
        else
        {
            return null;
        }
    }

    /**
     * This will store the value to the specified key.
     *
     * Note, that a _copy_ is stored,
     * the actual object is not referenced in the session data. You will have to update
     * it manually in case of changes.
     *
     * @param string $domain    The domain in which to search for the key.
     * @param mixed    $key        Session value identifier.
     * @param mixed    $value        Session value.
     */
    public function set($domain, $key, $value)
    {
        if (!$this->enabled)
        {
            return;
        }

        static $no_cache = false;
        if (!$no_cache)
        {
            // midgardmvc_core::get_instance()->cache->content->no_cache();
            $no_cache = true;
        }

        $this->data[$domain][$key] = serialize($value);
        $this->dispatcher->session_set_var(self::ROOT, $this->data);
    }
}


?>
