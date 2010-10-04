Midgard Web Framework
=====================

Midgard MVC applications are written in the PHP language, and consist of a collection of loadable modules, or _components_.

Basic building blocks:
----------------------

* Application: a configuration file describing the components and other settings used in a Midgard MVC website
* Component: a PHP module intended to run as part of a web application. For example: a news listing. Provides routes, controllers and templates
* Library: a PHP module that can be called by other modules to perform some task. Does not run as part of a website. For example: a form validation library
* Service: a standardized interface to access a given functionality. For example: authentication
* Provider: a standardized interface to access given information. For example: hierarchy. The difference between Services and Providers is that Services perform tasks while Providers merely provide access to a particular type of data

Installation
------------

* Download Midgard MVC and copy it to a place where your web server can access the files
* Download and [install PHPTAL](http://phptal.org/download.html) for TAL templating support
* If you're using Midgard MVC with the Midgard Content Repository, as you probably are:
  - [Install php5-midgard2](http://download.opensuse.org/repositories/home:/midgardproject:/ratatoskr/) for your distribution (or compile from sources)
  - Enable the Midgard2 extension in your `php.ini` with an `extension=midgard2.so`
  - Copy Midgard MVC database definitions (`midgardmvc_core/configuration/mgdschema.xml`) to the Midgard schema directory (by default `/usr/share/midgard2/schema/`)

Application configuration
-------------------------

Application configuration is a configuration file read before Midgard MVC starts where you can define application-wide shared configuration settings, including overriding Midgard MVC default configurations. The components used with an application are defined in the application configuration file.

The application configuration is by default located in the root of your Midgard MVC installation directory in a YAML file called `application.yml`. Example:

    name: Example Blog
    components:
        midgardmvc_core
            source: git://github.com/midgardproject/midgardmvc_core.git
        midgardmvc_create
    services_dispatcher: midgard3
    providers_component: midgardmvc

Running Midgard MVC
-------------------

Midgard MVC is usually run from a _rootfile_ that is set as the target of a rewrite rule on your web server. With lighttpd, the rule might look like the following:

    url.rewrite-once = ( "^/midgardmvc-static/(.*)$" => "/midgardmvc-static/$1",   "^(.*)\.*" => "midgard-root.php")

The folder `midgardmvc_core/httpd` contains example rootfiles for different setups. A barebones rootfile would look like the following:

    <?php
    // Load Midgard MVC
    // Note: your Midgard MVC base directory has to be in PHP include_path
    require('midgardmvc_core/framework.php');
    $config = array
    (
        'services_dispatcher' => 'midgard3',
        'providers_component' => 'midgardmvc',
    );
    $midgardmvc = midgardmvc_core::get_instance($config);
        
    // Process the request
    $request = $midgardmvc->process();

    // Serve the request
    $midgardmvc->serve($request);

    // End
    unset($midgardmvc);
    ?>

If you want to use Midgard content repository together with Midgard MVC, ensure that you have the `midgard2` extension enabled in your `php.ini` with `midgard.configuration_file` pointing to your database configuration and `midgard.http=On`.

### Running Midgard MVC with the PHP AppServer

It is also possible to run Midgard MVC using the PHP-based AppServer as your web server. In that case you need an installation of the AppServer in your PHP include path, and just have to run the following command:

    $ php midgardmvc_core/httpd/midgardmvc-root-appserv.php

### Midgard MVC request process

* Bootstrapping
  * Midgard MVC bootstrap PHP file (`framework.php`) is called
  * Midgard MVC bootstrap registers an autoloader (`midgardmvc_core::autoload()`) that will be used for loading the necessary PHP files
  * Front controller (`midgardmvc_core::get_instance()`) starts
  * Front controller loads configuration from a configuration provider
  * Front controller loads component providers specified in configuration
  * Front controller loads hierarchy providers specified in configuration
* Request processing
  * A request object gets populated with the current HTTP request parameters
  * Process injectors are called, if any
  * Request object uses hierarchy providers to determine what components handle the request
  * Dispatcher loads the necessary component
  * Dispatcher dispatches the request to the component controller class, passing it the request object
  * Component controller class executes and sets data to the request object
* Templating
  * Front controller loads template providers specified in configuration
  * Template injectors are called, if any
  * Front controller determines template to be used with the request
  * Front controller uses a template provider to generate request output
  * Request output is sent to browser

### Midgard MVC Front Controller

The Midgard MVC Front Controller `midgardmvc_core` is responsible for catching a HTTP request and ensuring it gets processed and templated. The Front Controller implements a Singleton pattern, meaning that only a single Midgard MVC Front Controller is available at a time.

The Front Controller is accessible via:

    $mvc = midgardmvc_core::get_instance();

### Midgard MVC Dispatcher

The Midgard MVC Dispatcher receives a Request object and instantiates and calls the necessary Components and Controllers, and calls their action methods.

The Dispatcher is accessible via:

    $dispatcher = midgardmvc_core::get_instance()->dispatcher;
    $dispatcher->dispatch($request);

Depending on what Controllers and action methods were called (if any), this will either return the Request object with some new data populated or cause an Exception to be thrown.

Component structure
-------------------

A component is a functional module that runs inside Midgard MVC. It is usually run associated to a particular Midgard MVC Node, but can also tack itself to be run alongside another component's Node.

* component_name
  * `manifest.yml`: Component's package manifest, routes and signal listener registration
  * configuration
     - `defaults.yml`: Component's default configuration, as name-value pairs
  * controllers
     - `controllername.php`: A controller class for the component
  * models
     - `classname.xml`: Midgard Schema used by the component, registers type `classname`
     - `viewname.xml`: Midgard View used by the component, registers view `viewname`
     - `classname.php`: PHP class that extends a Midgard Schema
  * services
     - `authentication.php`: component-specific implementation of Midgard MVC Authentication Service
  * templates
     - `templatename.xhtml`: A TAL template used by the component, named `templatename`

Routes
------

Individual routes (or views) of a component are defined in the component manifest. Midgard MVC takes the route definitions and constructs Route objects out of them.

Routes map between an URL and the corresponding controller class and an action method.

Minimal route definition:

    route_identifier:
        - path: '/some/url'
        - controller: controller class
        - action: action name
        - template_aliases:
            - root: name of template used when "root" is included
            - content: name of template used when "content" is included

### Route matching

There are several ways Midgard MVC matches Requests to Routes. The matching is handled by providing an Intent to the factory method of the Request class:

* Explicit matching
  * In an explicit match we know the component instance, route identifier and arguments
* Implicit matching
  * In implicit matching we know one or multiple of:
     - Route identifier and arguments
     - URL
     - Component name
     - Existing request object

### URL patterns

Route path (under a given hierarchy node) is defined with the path property of the route. The paths follow the [URL pattern specification](http://tools.ietf.org/html/draft-gregorio-uritemplate-04).

Variables can be used with URL patterns in the following way:

* Named variable
  - `-path: '/{$foo}'`
  - With request `/bar` the controller would be called with `$args['foo'] = 'bar'`
* Named and typed variable
  - `-path: '/latest/{int:$number}'`
  - With request `/latest/5` the controller would be called with `$args['number'] = 5`
* Unnamed arguments
  - -path `'/file/@'`
  - With request `/file/a/b` the controller would be called with `$args['variable_arguments'] = array('a', 'b')`

Workings of a controller
------------------------

Controller is a PHP class that contains one or more actions matching route definitions of a component. When invoked, Midgard MVC dispatcher will load the component, instantiate the controller class specified in route definition, passing it the Request object and a reference to request data array, and finally call the action method corresponding to the route used, passing it the arguments from the request.

The controller will then do whatever processing or data fetching it needs to do. Any content the controller wants to pass to a template should be added to the data array. If any errors occur, the controller should throw an exception. 

Actions are public methods in a controller class. Action methods are named using pattern `<HTTP verb>_<action name>`, for example `get_article()` or `post_form()`. Action methods will receive the possible URL arguments as an argument containing an array.

Here is a simple example. Route definition from `net_example_calendar/manifest.yml`:

    show_date:
        - path: '/date'
        - controller: net_example_calendar_controllers_date
        - action: date
        - template_aliases:
            - content: show-date

Controller class `net_example_calendar/controllers/date.php`:

    <?php
    class net_example_calendar_controllers_date
    {
        public function __construct(midgardmvc_core_request $request)
        {
            $this->request = $request;
        }

        public function get_date(array $args)
        {
            $this->data['date'] = strftime('%x %X');
        }
    }

Showtime
--------

Once a controller has been run, the next phase in MVC execution is templating. There are two levels of templates used:

* Template entry point: the "whole page" template, which includes a content area by having a `<mgd:include>content</mgd:include>`
* Content entry point: the "content area" of a page, as defined in the main template

Each route definition can decide what templates to use in each of these. If a route wants to override the whole site template, then the route should define its own template entry point, and if it only wants to show something in the content area, then it should define its own content entry point.

Templates are defined by giving them a name. For example, a template for displaying the current date could be called `show-date`. When MVC gets into the templating stage. This is defined in the route:

    show_date:
        - path: '/date'
        - controller: net_example_calendar_date
        - action: date
        - template_aliases:
            - content: show-date

When the templating phase of the route happens, MVC will look for such element from the template stack. Template stack is a list of components running with the current request. First MVC looks for the element in the current component, and if it can't be found there it goes looking for it down the stack:

* Current running component `templates` directory
* `templates` directories of any components injected to the template stack
* Midgard MVC core `templates` directory

The first matching template element will be used and executed via TAL. The data returned by the component will be exposed into TAL as a `current_component` variable. In case of our date example the template could simply be a `net_example_calendar/templates/show-date.xhtml` file with following contents:

    <p>Current date is <span tal:content="current_component/date">5/8/1999 01:00</span></p>

Request isolation and making of sub-requests
--------------------------------------------

Midgard MVC supports handling multiple requests within same web page. For example, the main content of your page can be served from a request, and then a news listing in a sidebar can be handled from a sub-request.

Since this means that potentially multiple routes, controllers and templates will be run within the same PHP execution, every request must be isolated within the PHP variable scope. To accomplish this, the principle is that all request-specific information is stored within the Request object that gets passed around between the front controller, dispatcher and actual controllers, and all of them are actually stateless. For example, the `dispatch` method of a dispatcher, or the `template` method of the front controller may be run multiple times within same PHP execution.

Within any stage of Midgard MVC execution you can make a sub-request in the following way:

    <?php
    // Set up intent, for example a hierarchy node, node URL or component name
    $intent = '/myfolder/date';
    // Get a Request object based on the intent
    $request = midgardmvc_core_request::get_for_intent($intent);
    // Process the Request
    midgardmvc_core::get_instance()->dispatcher->dispatch($request);
    // Use the resulting data
    $component_data = $request->get_data_item('current_component');
    echo $component_data['date'];
    ?>

For convenience purposes there are two helpers for subrequest handling in the templating class. Dynamic call will perform the subrequest and return its data:

    $data = midgardmvc_core::get_instance()->templating->dynamic_call($intent, $route_id, $route_args);

Dynamic load will perform the subrequest and return its templated output:

    $content = midgardmvc_core::get_instance()->templating->dynamic_load($intent, $route_id, $route_args, true);

Error handling
--------------

Midgard MVC strives to encourage safe PHP programming practices. Because of this, all PHP code run under the framework is E_ALL error reporting level. This means that using unassigned variables and other similar sources of potential bugs will cause PHP errors to be shown on pages.

The actual application-level error handling happens with Exceptions. In any phase of application execution you can stop the processing by throwing an Exception appropriate to the situation. This will cause an error page to be shown and the error to be logged by MVC.

Typical Exceptions used with Midgard MVC are:

* `midgardmvc_exception_notfound`: Requested content could not be found. Shows a HTTP 404 error page.
* `midgardmvc_exception_unauthorized`: User tried to perform an unauthorized operation. Shows a HTTP 401 error page with a possibility for logging in.
* `midgardmvc_exception_httperror`: Some other type of HTTP error. The desired HTTP error code should be provided as the second parameter to the exception constructor.

### Example of throwing appropriate Exceptions in your controller

    public function post_comment(array $args)
    {
        $article = new net_example_article($args['article']);
        if (!$article->guid)
        {
            throw new midgardmvc_exception_notfound("Article {$args['article']} could not be found");
        }

        if (!midgardmvc_core::get_instance()->authorization->can_do('mgd:create', $article))
        {
            throw new midgardmvc_exception_unauthorized("You are not allowed to comment article {$article->title}");
        }

        // Implement saving a new comment to the article here
    }

Saving route state
------------------

*Note*: State saving is not yet implemented in Midgard MVC.

Although web by itself is stateless, in Midgard MVC a route can save its state in two different ways:

* Saving route's request data between requests
* Saving route's output between requests

If route data is found from a saved state, then the controller action will be called with the data pre-populated. The controller action can then either use or ignore it as it sees fit. Typical usage for stored route data is avoiding unnecessary Midgard database queries for retrieving information that is already available.

If route output is found from a saved state then Midgard MVC will return this output directly to the user and the controller action or the template will not be run.

### State audience

As there can be multiple users with different permissions and preferences interacting with the route the route can state what audience it wants to save its state for. The supported options are:

* public
  * Same state information is used for all users of that route
* private
  * State information is stored and used for each Midgard user separately
* session
  * State information is stored and used for each PHP session separately. This is basically same as `private` but also for non-authenticated users

Audience for a route can be communicated in the route definition:

    route_identifier:
        - cache_audience: public

If an audience is not defined Midgard MVC will treat the route as `private`.

### State invalidation

When saving state, a Route must inform Midgard MVC of either tags associated with the data or an expiry date, or both.

Midgard MVC will automatically invalidate all saved state information after it expires.

State tags are a free-form list of keywords associated with the state, and their invalidation must be handled by component developer himself. However, multiple components can use same state tags, and invalidating one of them will invalidate state from all routes that used the tag.
