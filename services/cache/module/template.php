<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Template caching module
 *
 * Provides a way to cache all elements collected by the Midgard MVC templating service to a file.
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_services_cache_module_template
{
    private $configuration = array();
    private $cache_directory = '';

    public function __construct(array $configuration)
    {
        $this->configuration = $configuration;
        if (!isset($this->configuration['directory']))
        {
            throw new Exception("Cache directory not configured");
        }

        $this->cache_directory = $this->get_cache_directory();
        if (!file_exists($this->cache_directory))
        {
            $res = @mkdir($this->cache_directory, 0777, true);
            if (false === $res)
            {
                throw new RuntimeException("Couldn't create '{$this->cache_directory}' cache directory");
            }
        }
        
        if (!is_writable($this->cache_directory))
        {
            throw new Exception("Cache directory {$this->cache_directory} is not writable");
        }
    }
    
    public function get_cache_directory()
    {
        $cache_root = null;
        if (extension_loaded('midgard2'))
        {
            $config = midgard_connection::get_instance()->config;
            if (   $config
                && $config->cachedir)
            {
                $cache_root = $config->cachedir;
            }
        }

        if (isset($_ENV['MIDGARD_ENV_GLOBAL_CACHEDIR']))
        {
            // Midgard Runtime
            $cache_root = $_ENV['MIDGARD_ENV_GLOBAL_CACHEDIR'];
        }

        if (is_null($cache_root))
        {
            // Safe fallback, use /tmp
            $cache_root = sys_get_temp_dir();
        }

        return str_replace('__MIDGARDCACHE__', $cache_root, $this->configuration['directory']);
    }
    
    public function check($identifier)
    {
        $cache_file = "{$this->cache_directory}/{$identifier}.php";
        
        // Template cache is very simple, no expiries needed
        return file_exists($cache_file);
    }
    
    public function get($identifier)
    {
        return "{$this->cache_directory}/{$identifier}.php";
    }
    
    public function put($identifier, $template)
    {
        $cache_file = "{$this->cache_directory}/{$identifier}.php";
        file_put_contents($cache_file, $template, LOCK_EX);
    }

    /**
     * Associate tags with a template file
     */
    public function register($identifier, array $tags)
    {
        // Associate the tags with the template ID
        foreach ($tags as $tag)
        {
            $identifiers = midgardmvc_core::get_instance()->cache->get('template', $tag);
            if (!is_array($identifiers))
            {
                $identifiers = array();
            }
            elseif (in_array($identifier, $identifiers))
            {
                continue;
            }
            $identifiers[] = $identifier;

            midgardmvc_core::get_instance()->cache->put('template', $tag, $identifiers);
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
            $identifiers = midgardmvc_core::get_instance()->cache->get('template', $tag);
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
            $cache_file = "{$this->cache_directory}/{$identifier}.php";
            if (file_exists($cache_file))
            {
                unlink($cache_file);
            }
        }
        return true;
    }

    /**
     * Remove all cached template files
     */
    public function invalidate_all()
    {
        $directory = dir($this->cache_directory);
        while (false !== ($entry = $directory->read()))
        {
            if (substr($entry, 0, 1) == '.')
            {
                // Ignore dotfiles
                continue;
            }

            if (is_dir("{$this->cache_directory}/{$entry}"))
            {
                // Ignore subdirectories
                continue;
            }
            
            // Just remove the template
            unlink("{$this->cache_directory}/{$entry}");
        }
        $directory->close();
        
        // Delete all tag/template mappings
        midgardmvc_core::get_instance()->cache->delete_all('template');
    }
}
?>
