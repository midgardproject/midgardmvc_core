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

    private $gettext_translator = array();

    private $midgardmvc = null;

    public function __construct()
    {
        $this->midgardmvc = midgardmvc_core::get_instance();
    }

    public function get_element_callback(array $element)
    {
        $request = $this->midgardmvc->context->get_request();

        if (is_a($request, 'midgardmvc_core_request'))
        {
            return $this->get_element($request, $element[1]);
        }
    }

    public function get_element(midgardmvc_core_request $request, $element, $handle_includes = true)
    {
        // Check for possible element aliases
        $route = $request->get_route();

        if (    $route
            && isset($route->template_aliases[$element]))
        {
            $element = $route->template_aliases[$element];
        }

        $component_chain = $request->get_component_chain();
        foreach ($component_chain as $component)
        {
            $element_content = $component->get_template_contents($element);
            if (is_null($element_content))
            {
                // This component didn't provide the necessary element, go to next one in stack
                continue;
            }

            if (!$handle_includes)
            {
                return $element_content;
            }

            // Replace instances of <mgd:include>elementname</mgd:include> with contents of the element
            return preg_replace_callback("%<mgd:include[^>]*>([a-zA-Z0-9_-]+)</mgd:include>%", array($this, 'get_element_callback'), $element_content);
        }
        throw new OutOfBoundsException("Element {$element} not found in Midgard MVC component chain.");
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

        $request = midgardmvc_core_request::get_for_intent($intent, $switch_context);
        $request->add_component_to_chain($this->midgardmvc->component->get('midgardmvc_core'));
        $request->set_subrequest();
        if ($switch_context)
        {
            $this->midgardmvc->context->create($request);
        }

        // Run process injector for this request too
        $this->midgardmvc->component->inject($request, 'process');

        // Find the matching route
        $routes = $this->midgardmvc->component->get_routes($request);
        if (!isset($routes[$route_id]))
        {
            throw new OutOfRangeException("Route {$route_id} not defined, we have: " . implode(', ', array_keys($routes)));
        }
        $request->set_arguments($routes[$route_id]->set_variables($arguments));
        $request->set_route($routes[$route_id]);

        $this->dispatcher->dispatch($request);

        $data = $request->get_data_item('current_component');
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
     * @param string $intent Component name or page GUID
     * @param string $route_id     Route identifier
     * @param array $arguments  Arguments to give to the route
     * @return $array data
     */
    public function dynamic_load($intent, $route_id, array $arguments, $return_html = false)
    {
        $request = midgardmvc_core_request::get_for_intent($intent);
        $this->midgardmvc->context->create($request);
        $data = $this->dynamic_call($request, $route_id, $arguments, false);

        $this->template($request, 'content');
        if ($return_html)
        {
            $output = $this->display($request, $return_html);
        }
        else
        {
            $this->display($request);
        }

        /*
         * Gettext is not context safe. Here we return the "original" textdomain
         * because in dynamic call the new component may change it
         */
        $this->midgardmvc->context->delete();

        $component = $this->midgardmvc->context->get_request()->get_component();
        if ($component)
        {
            $this->midgardmvc->i18n->set_translation_domain($component->name);
        }

        if ($return_html)
        {
            return $output;
        }
    }

    /**
     * Include the template based on either global or controller-specific template entry point.
     */
    public function template(midgardmvc_core_request $request, $element_identifier = 'root')
    {
        // Let injectors do their work
        $this->midgardmvc->component->inject($request, 'template');
        // Check if we have the element in cache already
        if (   !$this->midgardmvc->configuration->development_mode
            && $this->midgardmvc->cache->template->check($request->get_template_identifier()))
        {
            return;
        }

        // Register current page to cache

        $this->midgardmvc->cache->template->register($request->get_template_identifier(), array($request->get_component()->name));
        $element = $this->get_element($request, $element_identifier);
        // Template cache didn't have this template, collect it
        $this->midgardmvc->cache->template->put($request->get_template_identifier(), $element);
    }

    /**
     * Show the loaded contents using the template engine
     *
     * @param string $content Content to display
     */
    public function display(midgardmvc_core_request $request, $return_output = false)
    {
        $data =& $request->get_data();

        $template_file = $this->midgardmvc->cache->template->get($request->get_template_identifier());

        $fp = fopen($template_file, 'r');
        flock($fp, LOCK_SH);
        $content = stream_get_contents($fp);
        fclose($fp);

        if (strlen($content) == 0)
        {
            throw new midgardmvc_exception('Template from "'.$template_file.'" is empty!');
        }

        if ($this->midgardmvc->configuration->services_templating_engine == 'tal')
        {
            $content = $this->display_tal($request, $content, $data);
        }
        // TODO: Support for other templating engines like Smarty or plain PHP

        if (isset($data['cache_enabled']) && $data['cache_enabled'])
        {
            ob_start();
        }

        /*$filters = $this->midgardmvc->configuration->get('output_filters');
        if ($filters)
        {
            foreach ($filters as $filter)
            {
                foreach ($filter as $component => $method)
                {
                    $instance = $this->midgardmvc->component->get($component);
                    if (!$instance)
                    {
                        continue;
                    }
                    $content = $instance->$method($content);
                }
            }
        }*/

        if ($return_output)
        {
            return $content;
        }
        else
        {
            echo $content;
        }

        if (isset($data['cache_enabled']) && $data['cache_enabled'])
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

    private function display_tal(midgardmvc_core_request $request, $content, array $data)
    {
        // We use the PHPTAL class
        if (!class_exists('PHPTAL'))
        {
            require('PHPTAL.php');
        }

        $tal = new PHPTAL($request->get_template_identifier());
        $tal->setPhpCodeDestination($this->midgardmvc->cache->template->get_cache_directory());

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
        $tal->request = $request;

        // FIXME: Remove this once Qaiku has upgraded
        $tal->MIDCOM = $this->midgardmvc;

        foreach ($data as $key => $value)
        {
            $tal->$key = $value;
        }

        $tal->setSource($content);

        $translator =& $this->midgardmvc->i18n->set_translation_domain($request->get_component()->name);
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
