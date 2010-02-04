<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Midgard-based templating interface for Midgard MVC
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_services_templating_midgard implements midgardmvc_core_services_templating
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
        if (!isset($this->midgardmvc->context->host))
        {
            if (isset($this->midgardmvc->context->template_cache_prefix))
            {
                return "{$this->midgardmvc->context->template_cache_prefix}-{$this->midgardmvc->context->component}-{$this->midgardmvc->context->style_id}-" . $this->midgardmvc->context->get_current_context() . 
                "-{$this->midgardmvc->context->route_id}-{$this->midgardmvc->context->template_entry_point}-{$this->midgardmvc->context->content_entry_point}";
            }
            else
            {
                return "CLI-{$this->midgardmvc->context->component}-{$this->midgardmvc->context->style_id}-" . $this->midgardmvc->context->get_current_context() . 
                "-{$this->midgardmvc->context->route_id}-{$this->midgardmvc->context->template_entry_point}-{$this->midgardmvc->context->content_entry_point}";
            }
        }
        if (!isset($this->midgardmvc->context->page))
        {
            return "{$this->midgardmvc->context->host->id}-{$this->midgardmvc->context->component}-{$this->midgardmvc->context->style_id}-" . $this->midgardmvc->context->get_current_context() . 
                "-{$this->midgardmvc->context->route_id}-{$this->midgardmvc->context->template_entry_point}-{$this->midgardmvc->context->content_entry_point}";
        }
        if (isset($this->midgardmvc->context->route_id))
        {
            return "{$this->midgardmvc->context->host->id}-{$this->midgardmvc->context->page->id}-{$this->midgardmvc->context->style_id}-" . $this->midgardmvc->context->get_current_context() . 
                "-{$this->midgardmvc->context->route_id}-{$this->midgardmvc->context->template_entry_point}-{$this->midgardmvc->context->content_entry_point}";
        }
        return "{$this->midgardmvc->context->host->id}-{$this->midgardmvc->context->page->id}-{$this->midgardmvc->context->style_id}-" . $this->midgardmvc->context->get_current_context() . 
            "-{$this->midgardmvc->context->template_entry_point}-{$this->midgardmvc->context->content_entry_point}";
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

        // Add current component to templating stack
        if (!in_array($request->get_component(), $this->midgardmvc->configuration->services_templating_components))
        {
            $this->midgardmvc->templating->append_directory($this->midgardmvc->componentloader->component_to_filepath($this->midgardmvc->context->component) . '/templates');
        }

        if ($this->midgardmvc->configuration->services_templating_database_enabled)
        {
            // Add style and page to templating stack
            if (   isset($this->midgardmvc->context->style_id)
                && $this->midgardmvc->context->style_id)
            {
                $this->midgardmvc->templating->append_style($this->midgardmvc->context->style_id);
            }
            if (   isset($this->midgardmvc->context->page)
                && $this->midgardmvc->context->page)
            {
                $this->midgardmvc->templating->append_page($this->midgardmvc->context->page->id);
            }
        }
    }

    public function append_directory($directory)
    {
        if (!file_exists($directory))
        {
            throw new Exception("Template directory {$directory} not found.");
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
    
    public function append_style($style_id)
    {
        if (!$this->midgardmvc->configuration->services_templating_database_enabled)
        {
            return;
        }
        $stack = $this->midgardmvc->context->get_current_context();
        if (!isset($this->stacks[$stack]))
        {
            $this->stacks[$stack] = array();
        }
        $this->stacks[$stack]["st:{$style_id}"] = 'style'; 
        
        // TODO: $this->midgardmvc->context->subtemplate support (look up child style)
    }
    
    public function append_page($page_id)
    {
        if (!$this->midgardmvc->configuration->services_templating_database_enabled)
        {
            return;
        }
        if ($page_id != $this->midgardmvc->context->page->id)
        {
            // Register page to template cache        
            $page = new midgard_page($page_id);
            $this->midgardmvc->cache->template->register($this->get_cache_identifier(), array($page->guid));
        }

        $stack = $this->midgardmvc->context->get_current_context();
        if (!isset($this->stacks[$stack]))
        {
            $this->stacks[$stack] = array();
        }
        $this->stacks[$stack]["pg:{$page_id}"] = 'page';
    }
    
    private function get_element_style($style_id, $element)
    {
        $mc = midgard_element::new_collector('style', $style_id);
        $mc->add_constraint('name', '=', $element);
        $mc->set_key_property('value');
        $mc->add_value_property('guid');
        $mc->execute();
        $keys = $mc->list_keys();
        if (count($keys) == 0)
        {
            return null;
        }
        
        foreach ($keys as $value => $array)
        {
            // Register element to template cache
            $this->midgardmvc->cache->template->register($this->get_cache_identifier(), array($mc->get_subkey($value, 'guid')));

            return $value;
        }
    }    
    
    private function get_element_page($page_id, $element)
    {
        switch ($element)
        {
            case 'title':
            case 'content':
                $mc = midgard_page::new_collector('id', $page_id);
                $mc->set_key_property($element);
                $mc->execute();
                $keys = $mc->list_keys();
                if (count($keys) == 0)
                {
                    return null;
                }
                
                foreach ($keys as $value => $array)
                {
                    return $value;
                }
            default:
                $mc = midgard_pageelement::new_collector('page', $page_id);
                $mc->add_constraint('name', '=', $element);
                $mc->set_key_property('value');
                $mc->add_value_property('guid');
                $mc->execute();
                $keys = $mc->list_keys();
                if (count($keys) == 0)
                {
                    return null;
                }
                
                foreach ($keys as $value => $array)
                {
                    // Register element to template cache
                    $this->midgardmvc->cache->template->register($this->get_cache_identifier(), array($mc->get_subkey($value, 'guid')));

                    return $value;
                }
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
    
    private function get_element($element)
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

        // Reverse the stack in order to look for elements
        $reverse_stack = array_reverse($this->stacks[$stack], true);
        foreach ($reverse_stack as $identifier => $type)
        {
            $element_content = null;
            switch ($type)
            {
                case 'style':
                    $element_content = $this->get_element_style((int) substr($identifier, 3), $element);
                    break;
                case 'page':
                    $element_content = $this->get_element_page((int) substr($identifier, 3), $element);
                    break;
                case 'directory':
                    $element_content = $this->get_element_directory($identifier, $element);
                    break;
            }
            
            if (   $element_content
                && !in_array($element, $this->elements_shown))
            {
                if ($this->midgardmvc->firephp)
                {
                    $this->midgardmvc->firephp->log("Included template '{$element}' from {$type} {$identifier}");
                }

                $this->elements_shown[] = $element;

                $this->stack_elements[$stack][$element] = $element_content;
                
                // Replace instances of <mgd:include>elementname</mgd:include> with contents of the element
                return preg_replace_callback("%<mgd:include[^>]*>([a-zA-Z0-9_-]+)</mgd:include>%", array($this, 'get_element'), $this->stack_elements[$stack][$element]);
            }
        }
        
        //throw new OutOfBoundsException("Element {$element} not found in Midgard MVC style stack.");
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
     * @param string $component_name Component name, page GUID or page path
     * @param string $route_id     Route identifier
     * @param array $arguments  Arguments to give to the route
     * @param boolean $switch_context Whether to run the route in a new context
     * @return $array data
     */
    public function dynamic_call($component_name, $route_id, array $arguments, $switch_context = true)
    {
        if (is_null($this->dispatcher))
        {
            $this->dispatcher = new midgardmvc_core_services_dispatcher_manual();
        }
        
        if ($switch_context)
        {
            $this->midgardmvc->context->create();
        }

        $request = new midgardmvc_core_helpers_request();

        if (   is_object($component_name)
            && is_a($component_name, 'midgard_page'))
        {
            $request->set_page($component_name);
        }
        elseif (mgd_is_guid($component_name))
        {
            $request->set_page(new midgard_page($component_name));
        }
        elseif (strpos($component_name, '/') !== false)
        {
            $request->resolve_page($component_name);
        }
        else
        {
            $request->set_component($component_name);
        }
        
        // Copy HTTP request method of main context to the request
        $request->set_method($this->midgardmvc->context->get_item('request_method', 0));
        
        $request->populate_context();

        // Run process injector for this context too
        $this->midgardmvc->componentloader->inject_process();

        // Then initialize the component, so it also goes to template stack
        $this->dispatcher->initialize($request);

        $this->dispatcher->set_route($route_id, $arguments);
        $this->dispatcher->dispatch();

        $component_name = $this->midgardmvc->context->component;        
        $data = $this->midgardmvc->context->$component_name;

        if ($switch_context)
        {        
            $this->midgardmvc->context->delete();
        }
        
        return $data;
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
     * @param string $component_name Component name or page GUID
     * @param string $route_id     Route identifier
     * @param array $arguments  Arguments to give to the route
     * @return $array data
     */
    public function dynamic_load($component_name, $route_id, array $arguments)
    { 
        $this->midgardmvc->context->create();
        $data = $this->dynamic_call($component_name, $route_id, $arguments, false);

        $this->template('content_entry_point');
        $this->display();

        /* 
         * Gettext is not context safe. Here we return the "original" textdomain
         * because in dynamic call the new component may change it
         */
        $this->midgardmvc->context->delete();
        $this->midgardmvc->i18n->set_translation_domain($this->midgardmvc->context->component);
    }

    /**
     * Include the template based on either global or controller-specific template entry point.
     */    
    public function template($element_identifier = 'template_entry_point')
    {
        if ($this->midgardmvc->componentloader)
        {
            // Let injectors do their work
            $this->midgardmvc->componentloader->inject_template();
        }

        // Check if we have the element in cache already
        if (   !$this->midgardmvc->configuration->development_mode
            && $this->midgardmvc->cache->template->check($this->get_cache_identifier()))
        {
            return;
        }

        // Register current page to cache
        if (isset($this->midgardmvc->context->page))
        {
            $this->midgardmvc->cache->template->register($this->get_cache_identifier(), array($this->midgardmvc->context->page->guid));
        }
        else
        {
            $this->midgardmvc->cache->template->register($this->get_cache_identifier(), array($this->midgardmvc->context->component));
        }

        $element = $this->get_element($this->midgardmvc->context->$element_identifier);
        
        // Template cache didn't have this template, collect it
        $this->midgardmvc->cache->template->put($this->get_cache_identifier(), $element);
    }
    
    /**
     * Show the loaded contents using the template engine
     *
     * @param string $content Content to display
     */
    public function display()
    {
        $data =& $this->midgardmvc->context->get();

        $template_file = $this->midgardmvc->cache->template->get($this->get_cache_identifier());
        $content = file_get_contents($template_file);

        if ($data['template_engine'] == 'tal')
        {
            $content = $this->display_tal($content, $data);
        }
        // TODO: Support for other templating engines like Smarty or plain PHP

        if ($this->midgardmvc->context->cache_enabled)
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

        echo $content;
        
        if ($this->midgardmvc->context->cache_enabled)
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

    private function display_tal($content, $data)
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

        $translator =& $this->midgardmvc->i18n->set_translation_domain($this->midgardmvc->context->component);
        $tal->setTranslator($translator);  
    
        try
        {
            $content = $tal->execute();
        }
        catch (PHPTAL_TemplateException $e)
        {
            throw new Exception("PHPTAL: {$e->srcFile} line {$e->srcLine}: " . $e->getMessage());
        }
        
        return $content;
    }
}
?>
