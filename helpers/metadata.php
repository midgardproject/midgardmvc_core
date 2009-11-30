<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Metadata helper for MidCOM 3
 *
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_helpers_metadata
{
    public static function is_approved(&$object)
    {
        if ($object->metadata->approved >= $object->metadata->revised)
        {
            return true;
        }
        
        return false;
    }
    
    public static function approve(&$object)
    {
        midgardmvc_core::get_instance()->authorization->require_do('midcom:approve', $object);

        $object->approve();
    }
    
    public static function unapprove(&$object)
    {
        midgardmvc_core::get_instance()->authorization->require_do('midcom:approve', $object);

        $object->unapprove();
    }
    
    public static function is_locked(&$object, $check_locker = true)
    {
        if (empty($object->metadata->locked))
        {
            return false;
        }
        
        if (is_string($object->metadata->locked))
        {
            // Midgard1 ISO date string
            $lock_time = strtotime($object->metadata->locked . ' GMT');
        }
        else
        {
            // Midgard2 DateTime
            $lock_time = $object->metadata->locked->format('U');
        }
        $lock_timeout = $lock_time + (midgardmvc_core::get_instance()->configuration->get('metadata_lock_timeout') * 60);
        
        if (time() > $lock_timeout)
        {
            // Stale lock
            // TODO: Should we clear the stale lock here?
            return false;
        }
        
        if (   empty($object->metadata->locker)
            && $check_locker)
        {
            // Shared lock
            return false;
        }
        
        if (midgardmvc_core::get_instance()->authentication->is_user())
        {
            $person = midgardmvc_core::get_instance()->authentication->get_person();
            
            if (    $check_locker
                &&  (   $object->metadata->locker == $person->guid
                     || $object->metadata->locker == '')
                )
            {
                // If you locked it yourself, you can also edit it
                return false;
            }
        }
        
        return true;
    }
    
    public static function lock(&$object, $shared = false, $token = null)
    {
        midgardmvc_core::get_instance()->authorization->require_do('midgard:update', $object);
        
        if ($object->is_locked())
        {
            return;
        }

        $object->lock();
    }
    
    public static function unlock(&$object)
    {
        midgardmvc_core::get_instance()->authorization->require_do('midgard:update', $object);

        if (!$object->is_locked())
        {
            return;
        }

        $object->unlock();
    }
}
?>