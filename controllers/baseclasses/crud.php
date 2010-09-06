<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Base class for object management controller. Extend this to easily implement the regular Create, Read, Update and Delete cycle
 *
 * @package midgardmvc_core
 */
abstract class midgardmvc_core_controllers_baseclasses_crud
{
    /**
     * The actual MgdSchema object to be managed by the controller.
     */
    protected $object = null;
    
    /**
     * Datamanager instance
     */
    protected $datamanager = null;
    
    /**
     * Midgard MVC Forms instance
     */
    protected $form = null;

    public function __construct(midgardmvc_core_component_interface $instance)
    {
        $this->configuration = $instance->configuration;
    }

    /**
     * Method for loading the object to be managed. To be overridden in the actual controller.
     */
    abstract public function load_object(array $args);
    
    /**
     * Method for preparing a new object to be created. To be overridden in the actual controller.
     */
    abstract public function prepare_new_object(array $args);
    
    /**
     * Method for generating route to the object
     *
     * @return string Object URL
     */
    abstract public function get_url_read();

    /**
     * Method for generating route to editing the object
     *
     * @return string Object URL
     */    
    abstract public function get_url_update();
    
    public function load_form()
    {
        $this->form = midgardmvc_helper_forms_mgdschema::create($this->object);
    }

    // TODO: Refactor. There is code duplication with edit
    public function get_create(array $args)
    { 
        if (!isset(midgardmvc_core::get_instance()->context->page))
        {
            throw new midgardmvc_exception_notfound('No Midgard page found');
        }
        
        $this->data['object'] =& $this->object;
        $this->data['parent'] = midgardmvc_core::get_instance()->context->page;
        
        // Prepare the new object that form will eventually create
        $this->prepare_new_object($args);

        midgardmvc_core::get_instance()->authorization->require_do('midgard:create', $this->data['parent']);
        
        $this->load_form();
        $this->data['form'] =& $this->form;
    }

    public function post_create(array $args)
    {
        $this->get_create($args);
        try
        {
            $this->data['form']->process_post();
            midgardmvc_helper_forms_mgdschema::form_to_object($this->data['form'], $this->object);
            $this->object->create();
            
            // TODO: add uimessage of $e->getMessage();
            midgardmvc_core::get_instance()->head->relocate($this->get_url_read());
        }
        catch (midgardmvc_helper_forms_exception_validation $e)
        {
            // TODO: UImessage
        }
    }

    public function get_read(array $args)
    {
        $this->load_object($args);
        $this->data['object'] =& $this->object;
        
        if (   $this->data['object'] instanceof midgard_db_object
            && midgardmvc_core::get_instance()->authorization->can_do('midgard:update', $this->data['object']))
        {
            midgardmvc_core::get_instance()->head->add_link_head
            (
                array
                (
                    'rel' => 'alternate',
                    'type' => 'application/x-wiki',
                    'title' => 'Edit this page!', // TODO: l10n and object type
                    'href' => $this->get_url_update(),
                )
            );
        }
    }

    public function get_update(array $args)
    {
        $this->load_object($args);
        $this->data['object'] =& $this->object;
        midgardmvc_core::get_instance()->authorization->require_do('midgard:update', $this->object);
        
        $this->load_form();
        $this->data['form'] =& $this->form;
    }

    public function post_update(array $args)
    {
        $this->get_update($args);

        try
        {
            $this->data['form']->process_post();
            midgardmvc_helper_forms_mgdschema::form_to_object($this->data['form'], $this->object);
            $this->object->update();

            // FIXME: We can remove this once signals work again
            midgardmvc_core::get_instance()->cache->invalidate(array($this->object->guid));

            // TODO: add uimessage of $e->getMessage();
            midgardmvc_core::get_instance()->head->relocate($this->get_url_read());
        }
        catch (midgardmvc_helper_forms_exception_validation $e)
        {
            // TODO: UImessage
        }
    }
        
    public function get_delete(array $args)
    {
        $this->load_object($args);
        $this->data['object'] =& $this->object;
        
        // Make a frozen form for display purposes
        $this->load_form();
        //$this->form->freeze();
        $this->data['form'] =& $this->form;
        
        midgardmvc_core::get_instance()->authorization->require_do('midgard:delete', $this->object);
    }
    
    public function post_delete(array $args)
    {
        $this->get_delete($args);

        if (isset($_POST['delete']))
        {
            $this->object->delete();
            // FIXME: We can remove this once signals work again
            midgardmvc_core::get_instance()->cache->invalidate($this->object->guid);
            midgardmvc_core::get_instance()->head->relocate("{midgardmvc_core::get_instance()->context->prefix}/");
            // TODO: This needs a better redirect 
        }
    }
}
?>
