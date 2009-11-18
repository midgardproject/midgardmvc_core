Variants and actions
====================

## Variants

In MidCOM3 we're introducing a new concept of _variants_ into Midgard terminology. Variants are different views to same content object, determined based on URL request. Some examples:

* `articlename.html`: Full article as HTML
* `articlename.xml`: Full article as XML (Replicator serialization)
* `articlename.content.html`: Content field of article as HTML

The variants should be served as independent views to the content, and should support all the normal interaction that a content object has. For instance, if user has permission to update the article, he should be able to upload new article contents to the `articlename.content.html` URL as HTTP PUT.

TODO: How available variants are defined?

* Core-supplied variants like replicator XML
* Datamanager-supplied variants like `.content.html`
* Component's possible own variants

## Actions

An object, or a variant of an object should be able to have multiple actions assigned for it. The actions are a combination of:

* Route to the action (dispatcher `generate_url()` with needed arguments)
* HTTP method
* Possible POST or GET arguments to include
* Label for the action
* Icon for the action

Actions available for a given object or variant are determined by introspection. Any installed component can declare actions to provide to an object. For example, a _content versioning_ component could provide actions like _browse revisions_ and _restore revision_.

Component should list what classes it provides actions for in component manifest. Asterisk (`*`) can be used if actions apply for any  class:

    action_types:
        - midgard_page

Example of a `get_object_actions` method for a component interface class:

    public function get_object_actions(&$object, $variant = null)
    {
        $actions = array();
        if (!$_MIDCOM->authorization->can_do('midgard:update', $object))
        {
            // User is not allowed to edit so we have no actions available
            return $actions;
        }
        
        // This is the general action available for a page: forms-based editing
        $actions['edit'] = array
        (
            'url' => $_MIDCOM->dispatcher->generate_url('page_edit', array('name' => $object->name), $object),
            'method' => 'GET',
            'label' => $_MIDCOM->i18n->get('key: edit', 'midcom_core'),
            'icon' => 'midcom_core/stock_icons/16x16/edit.png',
        );

        return $actions;
    }

In general, URLs for actions should contain the `mgd:` prefix (`objectname/mgd:edit/` for example).

Actions available for an object can be for instance visualized in a toolbar. In addition, a Neutron-compatible XML file is available for all objects if Neutron Protocol is enabled. The Neutron XML file can be used for constructing UIs by external editing tools and for instance AJAX-based inspectors.

The editable objects inside a page can be identified by some attributes, so that external tools know which introspection files to load. The attributes to use are:

* `mgd:guid`: GUID of an object inside a given part of page
* `mgd:type`: MgdSchema type of the object inside a given part of page
* `mgd:label`: Display label (usually title) of the object inside a given part of page

Example:

    <li class="hentry" mgd:type="midgard_article" mgd:guid="18ea02405abe11dcb575adc2804439153915" mgd:label="Amer Sports 2007 financial calendar">
        <abbr title="2006-11-02T06:33:21+00:00" class="published">02/11/2006</abbr>
        <a href="/news/8ececd75e921702fa33460ab794cca94/" class="entry-title" rel="bookmark">Amer Sports 2007 financial calendar</a>
    </li>

A JSON introspection file showing actions for a given GUID can be fetched from `/mgd:actions/GUID.json`, in this case `/mgd:actions/18ea02405abe11dcb575adc2804439153915.json`. The actions JSON listing looks like the following:

    {
        "actions": {
            "edit": {
                "url": "\/news\/8ececd75e921702fa33460ab794cca94\/mgd:update",
                "method": "GET",
                "label": "key: edit",
                "icon": "midcom_core\/stock_icons\/16x16\/edit.png"
            },
            "delete": {
                "url": "\/news\/8ececd75e921702fa33460ab794cca94\/mgd:delete",
                "method": "GET",
                "label": "key: edit",
                "icon": "midcom_core\/stock_icons\/16x16\/trash.png"
            }
        }
    }

TODO: Example of Neutron document

## Navigation and object listings

In MidCOM3, navigation has three layers:

* Tree of pages (nodes)
* List of objects (leaves) and pseudo leaves under a page
* List of variants for an object

Each navigation item must have a unique identifier. In simple cases, the unique identifier can be an object GUID. In case of variants or pseudo leaves the identifier must contain the object's GUID and then some identifying information about the variant.

Each object and variant may also contain relations to other entries, linked using the navigation identifiers. For instance, a photo in a gallery may list a relation "next" pointing to next image in gallery. Relations used should generally conform to relations supported by Firefox "Site navigation" extension. TODO: List some supported examples.

With relations, a special identifier `mgd:current_page` points to GUID of the object's page. It can be used together with additional identifiers when pointin to a pseudo-leaf of the page.

Navigation information is used not only by components that draw a navigation for a site, but also by default handlers of PROPFIND requests to a website when WebDAV is enabled.

Some navigation methods:

    public function list_children(midgard_page $page);
    
    public function list_nodes(midgard_page $page);
    
    public function list_leaves(midgard_page $page);
    
    public function get_related($object_identifier, $relation);