<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Content caching module
 *
 * Provides a way to cache a page produced by Midgard MVC.
 *
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_services_cache_module_content
{
    private $configuration = array();
    private $cache_directory = '';

    public function __construct(array $configuration)
    {
        $this->configuration = $configuration;
    }

    public function check($identifier)
    {
        if (midgardmvc_core::get_instance()->context->request_method != 'GET')
        {
            return false;
        }

        if (!midgardmvc_core::get_instance()->cache->exists('content_metadata', $identifier))
        {
            // Nothing in meta cache about the identifier
            return false;
        }
        
        // Check the data for validity
        $meta = midgardmvc_core::get_instance()->cache->get('content_metadata', $identifier);
        
        if (   isset($data['expires'])
            && $data['expires'] < time())
        {
            // The contents in cache have expired
            return false;
        }
        
        // TODO: Check "not modified" and etag sent by browser
        
        // Check that we have the content
        if (!midgardmvc_core::get_instance()->cache->exists('content', $identifier))
        {
            // Nothing in meta cache about the identifier
            return false;
        }
        
        // TODO: Send the headers the original page sent

        // Serve the contents and exit
        echo midgardmvc_core::get_instance()->cache->get('content', $identifier);
        midgardmvc_core::get_instance()->dispatcher->end_request();
        exit(0);
    }
    
    public function put($identifier, $content)
    {
        if (!isset(midgardmvc_core::get_instance()->context->etag))
        {
            // Generate eTag from content
            midgardmvc_core::get_instance()->context->etag = md5($content);
        }

        // Store metadata
        $this->put_metadata($identifier);

        // Store the contents
        midgardmvc_core::get_instance()->cache->put('content', $identifier, $content);
    }
    
    private function put_metadata($identifier)
    {
        $metadata = array();
        
        // Store the expiry time
        $metadata['expires'] = time() + midgardmvc_core::get_instance()->context->cache_expiry;
        
        $metadata['etag'] = midgardmvc_core::get_instance()->context->etag;
        
        midgardmvc_core::get_instance()->cache->put('content_metadata', $identifier, $metadata);
    }

    /**
     * Associate tags with content
     */
    public function register($identifier, array $tags)
    {
        // Associate the tags with the template ID
        foreach ($tags as $tag)
        {
            $identifiers = midgardmvc_core::get_instance()->cache->get('content_tags', $tag);
            if (!is_array($identifiers))
            {
                $identifiers = array();
            }
            elseif (in_array($identifier, $identifiers))
            {
                continue;
            }
            $identifiers[] = $identifier;

            midgardmvc_core::get_instance()->cache->put('content_tags', $tag, $identifiers);
        }
    }

    /**
     * Invalidate all cached template files associated with given tags
     */
    public function invalidate(array $tags)
    {
        $invalidate = array();
        foreach ($tags as $tag)
        {
            $identifiers = midgardmvc_core::get_instance()->cache->get('content_tags', $tag);
            if ($identifiers)
            {
                foreach ($identifiers as $identifier)
                {
                    if (!in_array($identifier, $invalidate))
                    {
                        $invalidate[] = $identifier;
                    }
                }
            }
        }

        foreach ($invalidate as $identifier)
        {
            midgardmvc_core::get_instance()->cache->delete('content', $identifier);
            midgardmvc_core::get_instance()->cache->delete('content_metadata', $identifier);
            midgardmvc_core::get_instance()->cache->delete('content_tags', $identifier);
        }
    }

    /**
     * Remove all cached template files
     */
    public function invalidate_all()
    {
        // Delete all entries of both content, meta and tag cache
        midgardmvc_core::get_instance()->cache->delete_all('content');
        midgardmvc_core::get_instance()->cache->delete_all('content_metadata');
        midgardmvc_core::get_instance()->cache->delete_all('content_tags');
    }
}
?>
