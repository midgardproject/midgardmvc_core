<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Filesystem-based templating interface for Midgard MVC
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_services_templating_midgardmvc implements midgardmvc_core_services_templating
{
    private $dispatcher = null;
    private $stacks = array();
    private $stack_elements = array();

    private $elements_shown = array();

    private $gettext_translator = array();
    
    private $midgardmvc = null;

    public function __construct()
    {
        $this->stacks[0] = array();
        
        $this->midgardmvc = midgardmvc_core::get_instance();
    }

    public function get_cache_identifier()
    {
        return "{$this->midgardmvc->context->node->name}-{$this->midgardmvc->context->route_id}-{$this->midgardmvc->context->template_entry_point}-{$this->midgardmvc->context->content_entry_point}";
    }

    public function prepare_stack(midgardmvc_core_helpers_request $request)
    {
        // Set up initial templating stack
        if (   $this->midgardmvc->configuration->services_templating_components
            && is_array($this->midgardmvc->configuration->services_templating_components))
        {
            foreach ($this->midgardmvc->configuration->services_templating_components as $templating_component)
            {
                $this->midgardmvc->templating->append_directory($this->midgardmvc->componentloader->component_to_filepath($templating_component) . '/templates');
            }
        }

        // Add component (tree) to templating stack
        $components = $this->midgardmvc->componentloader->get_tree($request->get_component());
        $components = array_reverse($components);
        foreach ($components as $component)
        {
            // Walk through the inheritance tree and add all components to stack
            if (!in_array($component, $this->midgardmvc->configuration->services_templating_components))
            {
                $this->midgardmvc->templating->append_directory($this->midgardmvc->componentloader->component_to_filepath($component) . '/templates');
            }
        }
    }

    public function append_directory($directory)
    {
        if (!file_exists($directory))
        {
            throw new midgardmvc_exception("Template directory {$directory} not found.");
        }
        $stack = $this->midgardmvc->context->get_current_context();
        if (!isset($this->stacks[$stack]))
        {
            $this->stacks[$stack] = array();
        }
        $this->stacks[$stack][$directory] = 'directory';
        
        if (   isset($this->midgardmvc->context->subtemplate)
            && $this->midgardmvc->context->subtemplate
            && file_exists("{$directory}/{$this->midgardmvc->context->subtemplate}"))
        {
            $this->stacks[$stack]["{$directory}/{$this->midgardmvc->context->subtemplate}"] = 'directory';
        }
    }

    private function get_element_directory($directory, $element)
    {
        $path = "{$directory}/{$element}.xhtml";
        if (!file_exists($path))
        {
            return null;
        }
        return file_get_contents($path);
    }

    public function get_element($element, $handle_includes = true)
    {
        if (is_array($element))
        {
            // Element is array in the preg_replace_callback case (evaluating element includes)
            $element = $element[1];
        }

        $stack = $this->midgardmvc->context->get_current_context();
        if (!isset($this->stacks[$stack]))
        {
            throw new OutOfBoundsException("Midgard MVC style stack {$stack} not found.");
        }
        
        if (!isset($this->stack_elements[$stack]))
        {
            $this->stack_elements[$stack] = array();
        }
        
        if ($element == 'content')
        {
            $element = $this->midgardmvc->context->content_entry_point;
        }
        
        if (isset($this->stack_elements[$stack][$element]))
        {
            return $this->stack_elements[$stack][$element];
        }

        if (in_array($element, $this->elements_shown)) 
        {
            throw new midgardmvc_exception('"'.$element.'" is already shown');
        }

        // Reverse the stack in order to look for elements
        $reverse_stack = array_reverse($this->stacks[$stack], true);
        foreach ($reverse_stack as $identifier => $type)
        {
            $element_content = $this->get_element_directory($identifier, $element);

            if (   $element_content
                && !in_array($element, $this->elements_shown))
            {
                if ($this->midgardmvc->firephp)
                {
                    $this->midgardmvc->firephp->log("Included template '{$element}' from {$type} {$identifier}");
                }

                $this->elements_shown[] = $element;

                $this->stack_elements[$stack][$element] = $element_content;
                
                if ($handle_includes)
                {
                    // Replace instances of <mgd:include>elementname</mgd:include> with contents of the element
                    $this->stack_elements[$stack][$element] = preg_replace_callback("%<mgd:include[^>]*>([a-zA-Z0-9_-]+)</mgd:include>%", array($this, 'get_element'), $element_content);
                }
                
                return $this->stack_elements[$stack][$element];
            }
        }
        
        throw new OutOfBoundsException("Element {$element} not found in Midgard MVC style stack.");
    }

    /**
     * Call a route of a component with given arguments and return the data it generated
     *
     * Dynamic calls may be called for either a specific page that has a component assigned to it
     * by specifying a page GUID or path as the first argument, or to a static instance of a component
     * by specifying component name as the first argument.
     *
     * Here is an example of using dynamic calls inside a TAL template, in this case loading three latest news:
     * 
     * <code>
     * <tal:block tal:define="latest_news php:midgardmvc.templating.dynamic_call('net_nemein_news', 'latest', array('number' => 3))">
     *     <ul tal:condition="latest_news/news">
     *         <li tal:repeat="article latest_news/news">
     *             <a href="#" tal:attributes="href article/url" tal:content="article/title">Headline</a>
     *         </li>
     *     </ul>
     * </tal:block>
     * </code>
     *
     * @param string $intent Component name, node object, node GUID or node path
     * @param string $route_id     Route identifier
     * @param array $arguments  Arguments to give to the route
     * @param boolean $switch_context Whether to run the route in a new context
     * @return $array data
     */
    public function dynamic_call($intent, $route_id, array $arguments, $switch_context = true)
    {
        if (is_null($this->dispatcher))
        {
            $this->dispatcher = new midgardmvc_core_services_dispatcher_manual();
        }

        $request = midgardmvc_core_helpers_request::get_for_intent($intent);
        
        // Dynamic call with GET
        $request->set_method('get');

        // Run process injector for this request too
        $this->midgardmvc->componentloader->inject_process($request);

        // Then initialize the component, so it also goes to template stack
        $this->dispatcher->initialize($request);
        $this->dispatcher->set_route($route_id, $arguments);
        $this->dispatcher->dispatch($request);

        if ($switch_context)
        {        
            $this->midgardmvc->context->delete();
        }
        
        return $request->get_data_item($request->get_component());
    }
    
    /**
     * Call a route of a component with given arguments and display its content entry point
     *
     * Dynamic loads may be called for either a specific page that has a component assigned to it
     * by specifying a page GUID or path as the first argument, or to a static instance of a component
     * by specifying component name as the first argument.
     *
     * In a TAL template dynamic load can be used in the following way:
     *
     * <code>
     * <div class="news" tal:content="structure php:midgardmvc.templating.dynamic_load('/newsfolder', 'latest', array('number' => 4))"></div>
     * </code>
     *
     * @param string $intent Component name or page GUID
     * @param string $route_id     Route identifier
     * @param array $arguments  Arguments to give to the route
     * @return $array data
     */
    public function dynamic_load($intent, $route_id, array $arguments, $return_html = false)
    { 
        $this->midgardmvc->context->create();
        $data = $this->dynamic_call($intent, $route_id, $arguments, false);

        $this->template('content_entry_point');
        if ($return_html)
        {
            $output = $this->display($return_html);
        }
        else
        {
            $this->display();
        }

        /* 
         * Gettext is not context safe. Here we return the "original" textdomain
         * because in dynamic call the new component may change it
         */
        $this->midgardmvc->context->delete();
        $this->midgardmvc->i18n->set_translation_domain($this->midgardmvc->context->component);
        if ($return_html)
        {
            return $output;
        }
    }

    /**
     * Include the template based on either global or controller-specific template entry point.
     */    
    public function template(midgardmvc_core_helpers_request $request, $element_identifier = 'template_entry_point')
    {
        if ($this->midgardmvc->componentloader)
        {
            // Let injectors do their work
            $this->midgardmvc->componentloader->inject_template($request);
        }

        // Check if we have the element in cache already
        if (   !$this->midgardmvc->configuration->development_mode
            && $this->midgardmvc->cache->template->check($this->get_cache_identifier()))
        {
            return;
        }

        // Register current page to cache
        $this->midgardmvc->cache->template->register($this->get_cache_identifier(), array($request->get_component()));

        $element = $this->get_element($request->get_data_item($element_identifier));
        
        // Template cache didn't have this template, collect it
        $this->midgardmvc->cache->template->put($this->get_cache_identifier(), $element);
    }
    
    /**
     * Show the loaded contents using the template engine
     *
     * @param string $content Content to display
     */
    public function display(midgardmvc_core_helpers_request $request, $return_output = false)
    {
        $data =& $request->get_data();

        $template_file = $this->midgardmvc->cache->template->get($this->get_cache_identifier());
        $content = file_get_contents($template_file);

        if (strlen($content) == 0)
        {
            throw new midgardmvc_exception('Template from "'.$template_file.'" is empty!');
        }

        if ($this->midgardmvc->configuration->services_templating_engine == 'tal')
        {
            $content = $this->display_tal($request, $content, $data);
        }
        // TODO: Support for other templating engines like Smarty or plain PHP

        if ($data['cache_enabled'])
        {
            ob_start();
        }
        
        $filters = $this->midgardmvc->configuration->get('output_filters');
        if ($filters)
        {
            foreach ($filters as $filter)
            {
                foreach ($filter as $component => $method)
                {
                    $instance = $this->midgardmvc->componentloader->load($component);
                    if (!$instance)
                    {
                        continue;
                    }
                    $content = $instance->$method($content);
                }
            }
        }

        if ($return_output)
        {
            return $content;
        }
        else
        {
            echo $content;
        }
        
        if ($data['cache_enabled'])
        {
            // Store the contents to content cache and display them
            $this->midgardmvc->cache->content->put($this->midgardmvc->context->cache_request_identifier, ob_get_contents());
            ob_end_flush();
        }

        if ($this->midgardmvc->configuration->enable_uimessages)
        {
            // TODO: Connect this to some signal that tells the Midgard MVC execution has ended.
            $this->midgardmvc->uimessages->store();
        }
    }

    private function display_tal(midgardmvc_core_helpers_request $request, $content, array $data)
    {
        // We use the PHPTAL class
        if (!class_exists('PHPTAL'))
        {
            require('PHPTAL.php');
        }

        // FIXME: Rethink whole tal modifiers concept 
        include_once('TAL/modifiers.php');
        
        $tal = new PHPTAL($this->get_cache_identifier());
        
        $tal->uimessages = false;
        if ($this->midgardmvc->configuration->enable_uimessages)
        {
            if (   $this->midgardmvc->uimessages->has_messages()
                && $this->midgardmvc->uimessages->can_view())
            {
                $tal->uimessages = $this->midgardmvc->uimessages->render();
            }
        }

        $tal->midgardmvc = $this->midgardmvc;
        
        // FIXME: Remove this once Qaiku has upgraded
        $tal->MIDCOM = $this->midgardmvc;
        
        foreach ($data as $key => $value)
        {
            $tal->$key = $value;
        }

        $tal->setSource($content);

        $translator =& $this->midgardmvc->i18n->set_translation_domain($request->get_component());
        $tal->setTranslator($translator);  
    
        try
        {
            $content = $tal->execute();
        }
        catch (PHPTAL_TemplateException $e)
        {
            throw new midgardmvc_exception("PHPTAL: {$e->srcFile} line {$e->srcLine}: " . $e->getMessage());
        }
        
        return $content;
    }
}
?>
