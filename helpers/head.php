<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Head includes helper for Midgard MVC
 *
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_helpers_head
{
    private $link_head = array();
    private $link_head_urls = array();
    private $meta_head = array();
    private $configuration = null;

    private $js_head = array();
    
    private $prepend_script_head = array();
    private $script_head = array();
    
    // private $prepend_link_head = array();
    // private $link_head = array();
    
    private $enable_jquery_noconflict = false;
    private $jquery_inits = '';
    private $jquery_statuses = array();
    private $jquery_statuses_append = array();

    public $jquery_enabled = false;    
    
    public function __construct()
    {
        $this->link_head = array();
        $this->link_head_urls = array();
        $this->meta_head = array();
        $this->js_head = array();
        $this->prepend_script_head = array();
        $this->script_head = array();
        $this->enable_jquery_noconflict = false;
        $this->jquery_inits = '';
        $this->jquery_statuses = array();
        $this->jquery_statuses_append = array();
        $this->jquery_enabled = false;

        $this->configuration = midgardmvc_core::get_instance()->configuration;
        if ($this->configuration->enable_jquery_framework)
        {
            $this->enable_jquery();
        }
    }
    
    public function set_title($title)
    {
        $this->title = $title;   
    }
    
    public function enable_jquery($version = '1.4.2')
    {
        if ($this->jquery_enabled)
        {
            return;
        }
        
        if ($this->configuration->jquery_load_from_google)
        {
            // Let Google host jQuery for us
            $this->jquery_inits  = "        <script src=\"http://www.google.com/jsapi\"></script>\n";
            $this->jquery_inits .= "        <script>\n";
            $this->jquery_inits .= "            google.load('jquery', '{$version}');\n"; 
            $this->jquery_inits .= "        </script>\n";
        }
        else
        {
            // Load from jQuery bundled with Midgard MVC package
            $url = MIDGARDMVC_STATIC_URL . "/midgardmvc_core/jQuery/jquery-{$version}.min.js";
            $this->jquery_inits = "        <script type=\"text/javascript\" src=\"{$url}\"></script>\n";
        }
        
        $this->jquery_enabled = true;
    }
    
    /**
     * Register JavaScript snippets to jQuery states.
     *
     * This allows Midgard MVC components to register JavaScript code
     * to the jQuery states.
     * Possible ready states: document.ready
     *
     * @param string $script    The code to be included in the state.
     * @param string $state    The state where to include the code to. Defaults to document.ready
     * @see print_jquery_statuses()
     */
    public function add_jquery_state_script($script, $state = 'document.ready')
    {
        $js_call = "\n" . trim($script) . "\n";

        if (! isset($this->jquery_states[$state]))
        {
            $this->jquery_states[$state] = $js_call;
        }
        else
        {
            $this->jquery_states[$state] .= $js_call;
        }
    }

    function add_jsfile($url, $prepend = false)
    {
        // Adds a URL for a <script type="text/javascript" src="tinymce.js"></script>
        // like call. $url is inserted into src. Duplicates are omitted.
        if (! in_array($url, $this->js_head))
        {
            $js_call = "        <script type=\"text/javascript\" src=\"{$url}\"></script>\n";
            if ($prepend)
            {
                // Add the javascript include to the beginning, not the end of array
                array_unshift($this->js_head, $js_call);
            }
            else
            {
                $this->js_head[] = $js_call;
            }
        }
    }
    
    function add_script($script, $prepend = false, $type = 'text/javascript', $defer = '')
    {
        $js_call = "        <script type=\"{$type}\"{$defer}>\n";
        $js_call .= "        " . trim($script) . "\n";
        $js_call .= "        </script>\n";
        
        if ($prepend)
        {
            $this->prepend_script_head[] = $js_call;
        }
        else
        {
            $this->script_head[] = $js_call;
        }
    }

    /**
     * Register a meta to be placed in the html head.
     * Example to use this to include keywords:
     * <code>
     * $attributes = array
     * (
     *     'name' => 'keywords',
     *     'content' => 'midgard cms php open source',
     * );
     * $midcom->add_meta_head($attributes);
     * </code>
     *
     * @param array $attributes Array of attribute => value pairs to be placed in the tag.
     */
    public function add_meta(array $attributes)
    {
        if (!isset($attributes['name']))
        {
            return false;
        }

        $output = '        <meta';
        foreach ($attributes as $key => $val)
        {
            $output .= " {$key}=\"{$val}\" ";
        }
        $output .= "/>\n";
        
        $this->meta_head[] = $output;
    }

    /**
     * Register a link element to be placed in the html head.
     * Example to use this to include a css link:
     * <code>
     * $attributes = array
     * (
     *     'rel' => 'stylesheet',
     *     'type' => 'text/css',
     *     'href' => '/style.css'
     * );
     * $midcom->add_link_head($attributes);
     * </code>
     *
     * @param array $attributes Array of attribute => value pairs to be placed in the tag.
     */
    public function add_link(array $attributes, $prepend = false)
    {
        if (! array_key_exists('href', $attributes))
        {
            return false;
        }

        // Register each URL only once
        if (in_array($attributes['href'], $this->link_head_urls))
        {
            return false;
        }
        $this->link_head_urls[] = $attributes['href'];

        $output = '';

        if (array_key_exists('condition', $attributes))
        {
            $output .= "        <!--[if {$attributes['condition']}]>\n";
        }

        $output .= '        <link';
        foreach ($attributes as $key => $val)
        {
            if ($key == 'condition')
            {
                continue;
            }
            $output .= " {$key}=\"{$val}\" ";
        }
        $output .= "/>\n";

        if (array_key_exists('condition', $attributes))
        {
            $output .= "        <![endif]-->\n";
        }
        
        if ($prepend)
        {
            array_unshift($this->link_head, $output);
        }
        else
        {
            $this->link_head[] = $output;            
        }
        
        return true;
    }
    
    /**
     * Echo the head elements added.
     * This function echos the elements added by the add_(css|meta|link|js(file|script)|jquery)
     * methods.
     *
     * Place the method within the <head> section of your page.
     *
     * This allows Midgard MVC components to register HEAD elements
     * during page processing. The site style code can then query this queued-up code
     * at anytime it likes. The queue-up SHOULD be done during the code-init phase,
     * while the print_elements output SHOULD be included in the HTML HEAD area and
     * the HTTP onload attribute returned by print_jsonload SHOULD be included in the
     * BODY-tag. Note, that these suggestions are not enforced, if you want a JScript
     * clean site, just omit the print calls and you should be fine in almost all
     * cases.
     *
     * @see add_link
     * @see add_css
     * @see add_meta
     * @see add_jsfile()
     * @see add_script()
     */
    public function print_elements()
    {
        if ($this->jquery_enabled)
        {
            echo $this->jquery_inits;
        }
        
        if (!empty($this->prepend_script_head))
        {
            foreach ($this->prepend_script_head as $js_call)
            {
                echo $js_call;
            }
        }
        if (isset($this->title))
        {
            echo ("<title>{$this->title}</title>");
        }

        foreach ($this->script_head as $js_call)
        {
            echo $js_call;
        }

        foreach ($this->js_head as $js_call)
        {
            echo $js_call;
        }
        
        $this->print_jquery_statuses();
        
        foreach ($this->link_head as $link)
        {
            echo $link;
        }
        
        foreach ($this->meta_head as $meta)
        {
            echo $meta;
        }
    }
    
    /**
     * Echo the jquery statuses
     *
     * This function echos the scripts added by the add_jquery_state_script
     * method.
     *
     * This method is called from print_elements method.
     *
     * @see add_jquery_state_script
     * @see print_elements
     */
    public function print_jquery_statuses()
    {
        if (empty($this->jquery_states))
        {
            return;
        }

        echo "<script type=\"text/javascript\">\n";

        foreach ($this->jquery_states as $status => $scripts)
        {
            $status_parts = explode('.',$status);
            $status_target = $status_parts[0];
            $status_method = $status_parts[1];
            echo "\n" . 'jQuery(' . $status_target . ').' . $status_method . '(function() {'."\n";
            echo $scripts;
            echo "\n" . '});' . "\n";
        }

        echo "</script>\n";
    }
    
    public function relocate($url = null)
    {
        if (is_null($url))
        {
            $url = $_SERVER['REQUEST_URI'];
        }
        $app = midgardmvc_core::get_instance();
        $app->uimessages->store();
        $app->dispatcher->header("Location: $url");
        $app->dispatcher->end_request();
    }
}

?>
