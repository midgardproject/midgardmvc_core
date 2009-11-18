MidCOM 3 at a glance
====================

This document has been written as a quick [Midgardized][4] version of the nice "[Django at a glance][2]" tutorial, in order to explain how the new and [upcoming MidCOM 3 framework][3] works. In addition publishing this as [a blog post][19], this document will be maintained in the [MidCOM 3 version control tree][20].

MidCOM 3 has been designed to be an extensible and highly configurable CMS development environment. It provides several basic building blocks like toolbars, access controls and management of static content pages out-of-the-box.

## Create your component

MidCOM applications are built as "components" with [Java-like namespacing][6]. This means that a "news" component designed by company named Nemein could be called `net_nemein_news`. Components are run on a Midgard site by setting a folder of the site to use that component.

A fresh component can be generated using the [Phing][21] build tool. For example, to create the news component, run:

    $ phing scaffold -Dcomponent=net_nemein_news

This will create the directory and file structure needed for the component to work.

## Design your model

MidCOM uses Midgard's MgdSchema object-relational mapping system for providing easy object-oriented API for database storage. Models are described using [XML syntax][7], and Midgard then provides OOP APIs to them for C, [PHP][9] and [Python][10] languages.

Here is a simple example MdgSchema:

    <?xml version="1.0" encoding="UTF-8"?>
    <Schema xmlns="http://www.midgard-project.org/repligard/1.4">
      <type name="net_nemein_news_article" table="net_nemein_news_article">
        <property name="id" type="unsigned integer" primaryfield="id" />
        <property name="name" type="string" index="yes"/> 
        <property name="title" type="string" multilang="yes" table="net_nemein_news_article_i" />
        <property name="content" type="text" multilang="yes" table="net_nemein_news_article_i" />
      </type>    
    </Schema>

Other fields like _author_ or _publication date_ do not need to be defined in the MgdSchema as Midgard automatically extends all records with [a set of metadata properties][8].

## Install it

Place the MgdSchema XML file to `/usr/share/midgard/schema`, and then create the database tables based on it by running:

    $ midgard-schema midgard

Where `midgard` is the name of your [Midgard conf.d file][11] that defines the database access parameters like database type and password. You can also do the database update via the `/mgd:about/database` URL.

## Enjoy the free API

These steps are all you need to do to have new Midgard objects at your disposal. Now you can access and manipulate them using for example Python or PHP. With PHP, the API looks like this:

    <?php
    // Create a new person
    $reporter = new midgard_person();
    $reporter->firstname = 'John';
    $reporter->lastname = 'Smith';
    
    // Save it to the database
    $reporter->create();
    
    // Now it has an UUID
    echo $reporter->guid;
    
    // And it can be fetched from the database
    $qb = new midgard_query_builder('midgard_person')
    print_r($qb->execute());
    // This would print an array of midgard_person objects containing "John Smith"
    
    // We have multiple ways to query objects
    $qb->add_constraint('lastname', 'LIKE', 'Smi%');
    print_r($qb->execute());
    
    // Create a new article
    $article = new net_nemein_news_article();
    $article->title = 'MidCOM is cool';
    $article->metadata->authors = $reporter->guid;
    $article->create();
    
    // Extend the article with new properties
    $article->parameter('namespace', 'key', 'value');
    
    // Localize the article to Finnish
    midgard_connection::set_lang('fi');
    $article->title = 'MidCOM on ältsin magee';
    $article->update();
    
    // Delete the Finnish translation
    $article->delete();
    
    /// etc
    ?>

### And the same in Python

Since Midgard is a multilingual framework, same content can be accessed between PHP, Python and C APIs. Here is the Python version of the PHP example above:

    # -*- coding: utf-8 -*-
    import os, sys
    import _midgard as midgard
    
    # new config instance
    config = midgard.config()
    
    # read default configuration
    opened = config.read_file('midgard', True)
    
    if opened == False:
        raise SystemError("Failed to open default configuration file")
    
    # try to connect
    cnc = midgard.connection()
    connected = cnc.open_config(config)
    
    # Create a new person
    reporter = midgard.mgdschema.midgard_person()
    reporter.firstname = 'John'
    reporter.lastname = 'Smith'
    
    # Save it to the database
    reporter.create()
    
    # Now it has an UUID
    print(reporter.guid)
    
    # And it can be fetched from the database
    qb = midgard.query_builder('midgard_person')
    
    # We have multiple ways to query objects
    qb.add_constraint('lastname', 'LIKE', 'Smi%')
    list = qb.execute()
    print(list)
    
    #Create a new article
    article = midgard.mgdschema.midgard_article()
    article.title = 'MidCOM is cool'
    metadata = article.metadata
    metadata.authors = reporter.guid
    article.create()
    
    # Extend the article with new properties
    article.set_parameter('namespace', 'key', 'value')
    
    # Localize the article to Finnish
    cnc.set_lang('fi')
    article.title = 'MidCOM on ältsin magee'
    article.update()
    
    # Delete the Finnish translation
    article.delete()

## A dynamic admin interface

Midgard comes with [Asgard][18], an automated administrative interface which provides a full editing tool to all our installed MgdSchemas. If your define a tree model for your storage, it will even provide a nice navigation tree for them. For example:

    <?xml version="1.0" encoding="UTF-8"?>
    <Schema xmlns="http://www.midgard-project.org/repligard/1.4">
      <type name="net_nemein_news_article" table="net_nemein_news_article" parent="midgard_topic">
        <property name="id" type="unsigned integer" primaryfield="id" />    
        <property name="name" type="string" index="yes"/>
        <property name="title" type="string" multilang="yes" table="net_nemein_news_article_i" />
        <property name="content" type="text" multilang="yes" table="net_nemein_news_article_i" />
        <property name="topic" type="unsigned integer" link="midgard_topic:id" parentfield="topic"/>
      </type>    
    </Schema>

After this the `net_nemein_news_article` objects would be stored under topics, and could be browsed in the topic tree hierarchy.

Asgard understands linked properties, datetime fields, and other MgdSchema field types and provides appropriate editing tools for them. For example, linked fields automatically become search-based choosers.

At this point you can already start entering content to your new application using the Asgard tool.

## Design your URLs

A clean URL space is important in a modern web framework, and with Midgard clean URLs have been the norm even since the 90s.

In MidCOM your application URL space is set up as something called "routes". They are entered in YAML format to the component's configuration file, in this case `net_nemein_news/configuration/defaults.yml`. The routes follow the [IETF URI template draft][1] format.

For example, a route for displaying a particular article could be defined as:

    show:
        controller: net_nemein_news_controllers_article
        action: show
        route: '/{$name}/'
        content_entry_point: nnn-show-article

In this case, the URL to a news article would be `/foldername/articlename`.

The routes define many things about what MidCOM should do with the request. First of all, they define which controller PHP class, and which action method in it will deal with the request. Additionally the route can define a `template_entry_point` or `content_entry_point` to choose which template files will be used for displaying the page. Other things like templating language and MIME type can also be defined in the configuration file.

Routes are stored in configuration file so that they can be easily overridden on per-site, or even per-folder basis. This means that migration from some other CMS system is quite easy as the site can be configured to retain the old URL formats.

## Write your controllers

Each controller is responsible for doing one of two things: Populating a `data` array with content of the requested page, or raising an exception like `midcom_exception_notfound`.

The controller class in this case would be stored to `net_nemein_news/controllers/article.php`. 

    class net_nemein_news_controllers_article
    {
        public function __construct($instance)
        {
            // Make configuration of this component instance easily available
            $this->configuration = $instance->configuration;
        }

        /**
         * Handle HTTP GET requests for the "show" route
         */
        public function get_show($args)
        {
            $qb = net_nemein_news_article::new_query_builder();
            $qb->add_constraint('name', '=', $args['name']);        
            $articles = $qb->execute();        
            if (count($articles) == 0)
            {
                throw new midcom_exception_notfound("Article {$args['name']} not found.");
            }
            $this->data['article'] = $articles[0];
        }

After the controller has produced the data it will then be passed to the MidCOM templating system according to the entry points defined for the route.

## Design your templates

MidCOM uses the [Template Abstraction Language][13] (TAL) for its templating purposes. TAL is a very powerful templating system in the sense that it allows designers to build the site XML or XHTML templates and fill them with example data that will then be replaced with the real data when TAL is run.

In this case our template would be placed in `net_nemein_news/templates/nnn-show-article.xhtml`. It could contain something like:

    <div class="hentry">
        <h1 tal:content="net_nemein_news/article/title" class="entry-title">Headline</h1>
    
        <div tal:content="net_nemein_news/article/metadata/published" class="published">2007-08-01</div>
    
        <div tal:content="structure net_nemein_news/article/content" class="entry-content">
            Content
        </div>
    </div>

The XHTML attributes in the `tal` namespace [contain the rules][14] used by TAL for replacing the example data with the real data from the controller. The data array of the controller is exposed to tal using the component name, in this case `net_nemein_news`.

The default template supplied by the component [can be overridden][12] by placing an element named `nnn-show-article` to either the site main style, or as an element of the current folder.

## This is just the surface

This has been a very quick overview of the MidCOM framework. The third version framework is still very much evolving, but the [earlier versions][5] are already very rich with existing tools and features.

However, in near future MidCOM 3 will feature rich signal-based I/O event handling, Access Control Lists, caching and many other things useful to web developers.

Everybody interested in MidCOM 3 is welcome to install a [Midgard 8.09][15] or newer, [get a SVN checkout][16] of MidCOM 3 and [start playing][17].

[1]: http://www.ietf.org/internet-drafts/draft-gregorio-uritemplate-02.txt
[2]: http://www.djangoproject.com/documentation/overview/
[3]: http://bergie.iki.fi/blog/some_plans_for_midcom_3.html
[4]: http://www.midgard-project.org/
[5]: http://www.midgard-project.org/documentation/midcom
[6]: http://www.midgard-project.org/documentation/concepts-midcom-specs-architecture-namespacing/
[7]: http://www.midgard-project.org/documentation/mgdschema-file/
[8]: http://www.midgard-project.org/documentation/mgdschema-metadata-object/
[9]: http://www.midgard-project.org/documentation/mgdschema-in-php/
[10]: http://blogs.nemein.com/people/piotras/view/1178275038.html
[11]: http://www.midgard-project.org/documentation/unified-configuration/
[12]: http://www.midgard-project.org/documentation/concepts-page_and_style/
[13]: http://phptal.motion-twin.com/manual/en/
[14]: http://phptal.motion-twin.com/manual/en/#tal-namespace
[15]: http://www.midgard-project.org/download/
[16]: http://trac.midgard-project.org/browser/trunk/midcom
[17]: http://github.com/bergie/midcom/blob/67224f92c1520d94a3f50b1c25661a0504023610/midcom_core/documentation/usage.markdown
[18]: http://bergie.iki.fi/blog/building_a_new_admin_interface_for_midgard.html
[19]: http://bergie.iki.fi/blog/
[20]: http://trac.midgard-project.org/browser/trunk/midcom/midcom_core/documentation
[21]: http://phing.info/trac/