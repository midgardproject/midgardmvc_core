<?php
/**
 * @package midcom_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * MidCOM interface class
 *
 * @package midcom_core
 */
class midcom_core extends midcom_core_component_baseclass
{
    public function get_object_actions(midgard_page &$object, $variant = null)
    {
        $actions = array();
        if (!$_MIDCOM->authorization->can_do('midgard:update', $object))
        {
            // User is not allowed to edit so we have no actions available
            return $actions;
        }
        
        // This is the general action available for a page: forms-based editing
        $actions['update'] = array
        (
            'url' => $_MIDCOM->dispatcher->generate_url('page_update', array(), $object),
            'method' => 'GET',
            'label' => $_MIDCOM->i18n->get('update', 'midcom_core'),
            'icon' => 'midcom_core/stock_icons/16x16/update.png',
        );
        $actions['delete'] = array
        (
            'url' => $_MIDCOM->dispatcher->generate_url('page_delete', array(), $object),
            'method' => 'GET',
            'label' => $_MIDCOM->i18n->get('delete', 'midcom_core'),
            'icon' => 'midcom_core/stock_icons/16x16/delete.png',
        );
        
        return $actions;
    }

    public function get_system_actions(midgard_page $folder)
    {
        $actions = array();
        
        static $root_page = null;
        if (is_null($root_page))
        {
            $root_page = new midgard_page($_MIDCOM->context->root);
        }
        
        $actions['logout'] = array
        (
            'url' => $_MIDCOM->dispatcher->generate_url('logout', array(), $root_page),
            'method' => 'GET',
            'label' => $_MIDCOM->i18n->get('logout', 'midcom_core'),
            'icon' => 'midcom_core/stock_icons/16x16/exit.png',
        );
        
        return $actions;
    }

    public function get_create_actions(midgard_page $folder)
    {
        $actions = array();

        if (!$_MIDCOM->authorization->can_do('midgard:create', $folder))
        {
            // User is not allowed to create subfolders so we have no actions available
            return $actions;
        }
        
        $actions['page_create'] = array
        (
            'url' => $_MIDCOM->dispatcher->generate_url('page_create', array()),
            'method' => 'GET',
            'label' => $_MIDCOM->i18n->get('create folder', 'midcom_core'),
            'icon' => 'midcom_core/stock_icons/16x16/folder.png',
        );
        
        return $actions;
    }
}
?>