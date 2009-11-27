<?php
/**
 * @package midcom_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Midgard-based templating interface for MidCOM 3
 *
 * @package midcom_core
 */
class midcom_core_services_templating_midgard implements midcom_core_services_templating
{
    private $dispatcher = null;
    private $stacks = array();
    private $stack_elements = array();

    private $elements_shown = array();

    private $gettext_translator = array();
    
    private $midcom = null;

    public function __construct()
    {
        $this->stacks[0] = array();
        
        $this->midcom = midcom_core_midcom::get_instance();
    }

    public function get_cache_identifier()
    {
        if (!isset($this->midcom->context->host))
        {
            return "CLI-{$this->midcom->context->component}-{$this->midcom->context->style_id}-" . $this->midcom->context->get_current_context() . 
                "-{$this->midcom->context->route_id}-{$this->midcom->context->template_entry_point}-{$this->midcom->context->content_entry_point}";
        }
        if (!isset($this->midcom->context->page))
        {
            return "{$this->midcom->context->host->id}-{$this->midcom->context->component}-{$this->midcom->context->style_id}-" . $this->midcom->context->get_current_context() . 
                "-{$this->midcom->context->route_id}-{$this->midcom->context->template_entry_point}-{$this->midcom->context->content_entry_point}";
        }
        if (isset($this->midcom->context->route_id))
        {
            return "{$this->midcom->context->host->id}-{$this->midcom->context->page->id}-{$this->midcom->context->style_id}-" . $this->midcom->context->get_current_context() . 
                "-{$this->midcom->context->route_id}-{$this->midcom->context->template_entry_point}-{$this->midcom->context->content_entry_point}";
        }
        return "{$this->midcom->context->host->id}-{$this->midcom->context->page->id}-{$this->midcom->context->style_id}-" . $this->midcom->context->get_current_context() . 
            "-{$this->midcom->context->template_entry_point}-{$this->midcom->context->content_entry_point}";
    }

    public function append_directory($directory)
    {
        if (!file_exists($directory))
        {
            throw new Exception("Template directory {$directory} not found.");
        }
        $stack = $this->midcom->context->get_current_context();
        if (!isset($this->stacks[$stack]))
        {
            $this->stacks[$stack] = array();
        }
        $this->stacks[$stack][$directory] = 'directory';
        
        if (   isset($this->midcom->context->subtemplate)
            && $this->midcom->context->subtemplate
            && file_exists("{$directory}/{$this->midcom->context->subtemplate}"))
        {
            $this->stacks[$stack]["{$directory}/{$this->midcom->context->subtemplate}"] = 'directory';
        }
    }
    
    public function append_style($style_id)
    {
        if (!$this->midcom->configuration->services_templating_database_enabled)
        {
            return;
        }
        $stack = $this->midcom->context->get_current_context();
        if (!isset($this->stacks[$stack]))
        {
            $this->stacks[$stack] = array();
        }
        $this->stacks[$stack]["st:{$style_id}"] = 'style'; 
        
        // TODO: $this->midcom->context->subtemplate support (look up child style)
    }
    
    public function append_page($page_id)
    {
        if (!$this->midcom->configuration->services_templating_database_enabled)
        {
            return;
        }
        if ($page_id != $this->midcom->context->page->id)
        {
            // Register page to template cache        
            $page = new midgard_page($page_id);
            $this->midcom->cache->template->register($this->get_cache_identifier(), array($page->guid));
        }

        $stack = $this->midcom->context->get_current_context();
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
            $this->midcom->cache->template->register($this->get_cache_identifier(), array($mc->get_subkey($value, 'guid')));

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
                    $this->midcom->cache->template->register($this->get_cache_identifier(), array($mc->get_subkey($value, 'guid')));

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
        
        $stack = $this->midcom->context->get_current_context();
        if (!isset($this->stacks[$stack]))
        {
            throw new OutOfBoundsException("MidCOM style stack {$stack} not found.");
        }
        
        if (!isset($this->stack_elements[$stack]))
        {
            $this->stack_elements[$stack] = array();
        }
        
        if ($element == 'content')
        {
            $element = $this->midcom->context->content_entry_point;
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
                if ($this->midcom->firephp)
                {
                    $this->midcom->firephp->log("Included template '{$element}' from {$type} {$identifier}");
                }

                $this->elements_shown[] = $element;

                $this->stack_elements[$stack][$element] = $element_content;
                
                // Replace instances of <mgd:include>elementname</mgd:include> with contents of the element
                return preg_replace_callback("%<mgd:include[^>]*>([a-zA-Z0-9_-]+)</mgd:include>%", array($this, 'get_element'), $this->stack_elements[$stack][$element]);
            }
        }
        
        //throw new OutOfBoundsException("Element {$element} not found in MidCOM style stack.");
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
     * <tal:block tal:define="latest_news php:MIDCOM.templating.dynamic_call('net_nemein_news', 'latest', array('number' => 3))">
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
            $this->dispatcher = new midcom_core_services_dispatcher_manual();
        }
        
        if ($switch_context)
        {
            $this->midcom->context->create();
        }

        $page = null;

        if (mgd_is_guid($component_name))
        {
            $page = new midgard_page($component_name);
        }
        elseif (strpos($component_name, '/') !== false)
        {
            $page = new midgard_page();
            $page->get_by_path($component_name);
        }
        
        if ($page)
        {
            $component_name = $page->component;
            
            if (!$component_name)
            {
                throw new Exception("Page {$page->guid} has no component defined");
            }
            
            $this->dispatcher->set_page($page);
        }

        $this->dispatcher->populate_environment_data();

        // Run process injector for this context too
        $this->midcom->componentloader->inject_process();

        // Set up initial templating stack
        if (   $this->midcom->configuration->services_templating_components
            && is_array($this->midcom->configuration->services_templating_components))
        {
            foreach ($this->midcom->configuration->services_templating_components as $templating_component)
            {
                $this->midcom->templating->append_directory(MIDGARDMVC_ROOT . "/{$templating_component}/templates");
            }
        }

        // Then initialize the component, so it also goes to template stack
        $this->dispatcher->initialize($component_name);

        if (   $this->midcom->configuration->services_templating_database_enabled
            && isset($this->midcom->context->style_id))
        {
            // And finally append style and page to template stack
            $this->midcom->templating->append_style($this->midcom->context->style_id);    
            if ($page)
            {
                $this->midcom->templating->append_page($this->midcom->context->page->id);
            }
        }
        
        if (!$this->midcom->context->component_instance->configuration->exists('routes'))
        {
            throw new Exception("Component {$component_name} has no routes defined");
        }
        
        $routes = $this->midcom->context->component_instance->configuration->get('routes');
        if (!isset($routes[$route_id]))
        {
            throw new Exception("Component {$component_name} has no route {$route_id}");
        }

        $this->dispatcher->set_route($route_id, $arguments);
        $this->dispatcher->dispatch();
        
        $data = $this->midcom->context->$component_name;

        if ($switch_context)
        {        
            $this->midcom->context->delete();
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
     * <div class="news" tal:content="structure php:MIDCOM.templating.dynamic_load('/newsfolder', 'latest', array('number' => 4))"></div>
     * </code>
     *
     * @param string $component_name Component name or page GUID
     * @param string $route_id     Route identifier
     * @param array $arguments  Arguments to give to the route
     * @return $array data
     */
    public function dynamic_load($component_name, $route_id, array $arguments)
    {
        $this->midcom->context->create();
        $data = $this->dynamic_call($component_name, $route_id, $arguments, false);

        $this->template('content_entry_point');
        $this->display();

        /* 
         * Gettext is not context safe. Here we return the "original" textdomain
         * because in dynamic call the new component may change it
         */
        $this->midcom->context->delete();
        $this->midcom->i18n->set_translation_domain($this->midcom->context->component);
    }

    /**
     * Include the template based on either global or controller-specific template entry point.
     */    
    public function template($element_identifier = 'template_entry_point')
    {
        if ($this->midcom->componentloader)
        {
            // Let injectors do their work
            $this->midcom->componentloader->inject_template();
        }

        // Check if we have the element in cache already
        if (   !$this->midcom->configuration->development_mode
            && $this->midcom->cache->template->check($this->get_cache_identifier()))
        {
            return;
        }

        // Register current page to cache
        if (isset($this->midcom->context->page))
        {
            $this->midcom->cache->template->register($this->get_cache_identifier(), array($this->midcom->context->page->guid));
        }
        else
        {
            $this->midcom->cache->template->register($this->get_cache_identifier(), array($this->midcom->context->component));
        }

        $element = $this->get_element($this->midcom->context->$element_identifier);
        
        // Template cache didn't have this template, collect it
        $this->midcom->cache->template->put($this->get_cache_identifier(), $element);
    }
    
    /**
     * Show the loaded contents using the template engine
     *
     * @param string $content Content to display
     */
    public function display()
    {
        $data =& $this->midcom->context->get();

        $template_file = $this->midcom->cache->template->get($this->get_cache_identifier());
        $content = file_get_contents($template_file);

        switch ($data['template_engine'])
        {
            case 'tal':
                $content = $this->display_tal($content, $data);
                
                break;
            default:
                break;
        }

        if ($this->midcom->context->cache_enabled)
        {
            ob_start();
        }
        
        $filters = $this->midcom->configuration->get('output_filters');
        if ($filters)
        {
            foreach ($filters as $filter)
            {
                foreach ($filter as $component => $method)
                {
                    $instance = $this->midcom->componentloader->load($component);
                    if (!$instance)
                    {
                        continue;
                    }
                    $content = $instance->$method($content);
                }
            }
        }

        echo $content;
        
        if (   $this->midcom->context->get_current_context() == 0
            && $this->midcom->context->mimetype == 'text/html')
        {
            // We're in main request, and output is HTML, so it is OK to inject some HTML to it
            if ($this->midcom->configuration->get('enable_included_list'))
            {
                $included = get_included_files();
                $this->midcom->log('midcom_services_templating::display', count($included) . " included files", 'info');
                foreach ($included as $filename)
                {
                    $this->midcom->log('midcom_services_templating::display::included', $filename, 'debug');
                }
            }
        }
        
        if ($this->midcom->context->cache_enabled)
        {
            // Store the contents to content cache and display them
            $this->midcom->cache->content->put($this->midcom->context->cache_request_identifier, ob_get_contents());
            ob_end_flush();
        }

        if ($this->midcom->configuration->enable_uimessages)
        {
            ///TODO: Connect this to some signal that tells the MidCOM execution has ended.
            $this->midcom->uimessages->store();
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
        if ($this->midcom->configuration->enable_uimessages)
        {
            if (   $this->midcom->uimessages->has_messages()
                && $this->midcom->uimessages->can_view())
            {
                $tal->uimessages = $this->midcom->uimessages->render();
            }
        }

        $tal->MIDCOM = $this->midcom;
        
        foreach ($data as $key => $value)
        {
            $tal->$key = $value;
        }

        $tal->setSource($content);

        $translator =& $this->midcom->i18n->set_translation_domain($this->midcom->context->component);
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
