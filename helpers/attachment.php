<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Basic attachment helpers for MidCOM 3
 *
 * Class provides some basic helpers for everyday attachments usage like getting the attachment serving uri
 * or it provides methods for caching attachment to some internal or external storage
 *
 *
 * @package midgardmvc_core
 */

interface midgardmvc_core_attachment
{
    /**
      * Function implements signal connection. It should connect to on-created and on-deleted signals
      * and map them to correspondent internal handler function
      */
    public function connect_to_signals();
    
    /**
      * Returns the URL where the attachment is found. If attachment's permission for EVERYONE:READ is false
      * then the MidCOM's internal attachment serving URI should be returned.
      */
    public static function get_url(midgard_attachment &$attachment);

    /**
      * Adds attachment to caching backend. Function should return false if attachment's permission for EVERYONE:READ is false.
      * It is required that cached attachments are public. 
      *
      * @param attachment midgard_attachment object
      */
    public static function add_to_cache(midgard_attachment &$attachment);

    /**
      * Removes attachment from the caching backend
      * @param attachment midgard_attachment object
      */
    public static function remove_from_cache(midgard_attachment &$attachment);
    
} 

class midgardmvc_core_helpers_attachment implements midgardmvc_core_attachment
{
    public function __construct() {}
    
    public function connect_to_signals()
    {
        midgard_object_class::connect_default('midgard_attachment', 'action-created', array(
            $this, 'on_creating'
        ), array(
            'attachment'
        ));

        midgard_object_class::connect_default('midgard_attachment', 'action-deleted', array(
            $this, 'on_deleting'
        ), array(
        ));
        
        // TODO: attachment cache needs undelete support
        // TODO: attachment cache needs approvals support
    }
    
    private function on_creating(midgard_attachment $attachment, $params)
    {
        if ($_MIDCOM->authorization->can_do('midgard:read', $attachment, null))
        {
            midgardmvc_core_helpers_attachment::add_to_cache($attachment);
        }
    }
    
    // TODO: Undelete support. Basically same as create
    private function on_deleting(midgard_attachment $attachment, $params)
    {
        midgardmvc_core_helpers_attachment::remove_from_cache($attachment);   
    }    

    /**
      * Returns the url where the attachment can be found
      *
      * @param midgard_attachment $attachment An attachment object
      * @return string url
      */
    public static function get_url(midgard_attachment &$attachment)
    {
        // Cheking if cache is enabled and attachment is readable for anonymous users
        if ($_MIDCOM->configuration->enable_attachment_cache
            && $_MIDCOM->authorization->can_do('midgard:read', $attachment, null))
        {
            return $_MIDCOM->configuration->attachment_cache_url . $attachment->location;
        }
        else // if cache is not enabled or anonymous read is not allowed serving attachment through MidCOM
        {
            return '/mgd:serveattachment/' . $attachment->guid . '/';
        }
    }
    
    /**
      * Links file to public web folder.
      * 
      * @param midgard_attachment attachment An attachment object
      * @return true of false 
      */
    public static function add_to_cache(midgard_attachment &$attachment)
    {
        $blob = new midgard_blob($attachment);
        
        // FIXME: Attachment directory creating should be done more elegantly
        $attachment_dir = explode('/', $attachment->location);
        $attachment_dir = $_MIDCOM->configuration->attachment_cache_directory . "{$attachment_dir[0]}/{$attachment_dir[1]}/";
        
        if (is_file($_MIDCOM->configuration->attachment_cache_directory.$attachment->location)) // checking if the link already exists
        {
            return false;
        }

        if(!is_dir($attachment_dir))
        {
            mkdir($attachment_dir, 0700, true);
        }
 
        return symlink ($blob->get_path(), $_MIDCOM->configuration->attachment_cache_directory.$attachment->location);
    }
    
    /**
      * Removes file from the public web folder
      *
      * @prarm midgard_attachment attachment An attachment object
      * @return true or false
      * 
      */
    public static function remove_from_cache(midgard_attachment &$attachment)
    {
        $filepath = $_MIDCOM->configuration->attachment_cache_directory.$attachment->location;     
        if (is_file ($filepath))
        {
            return unlink ($filepath);
        }
        else
        {
            return false;
        }
    }
    
}

?>