Quick MidCOM 3 setup HOWTO
==========================

1. Install Midgard 8.09 Ragnaroek
2. Create a new Midgard host with no MidCOM and a minimal style
3. Ensure the MidCOM 3 checkout is in php.ini `include_path`
4. Point the `MidgardRootFile` of the Midgard Apache VirtualHost setup to MidCOM's `httpd/midcom-root-midgard.php`
5. Patch your MgdObjects.xml (in `/usr/share/midgard`) to include a `component` property for `midgard_page` objects
6. Use `phing linkstatics` to link all CSS, JS and image files of MidCOM 3 distribution under `/usr/local/lib/midcom/static`
7. Then symlink that as `midcom-static` under your DocumentRoot 
8. Start playing around

Usage with Midgard2
-------------------

* Install lighttpd
* Install your PHP with cgi enabled
* Enable fastcgi in lighttpd
* Symlink `httpd/midcom-root-midgard2.php` under your documentroot
* Set up a virtualhost:

        $HTTP["host"] == "example.net" {
            server.port = 81
            server.document-root = "/opt/local/var/lib/midgard/vhosts/example.net/81/"
            url.rewrite-once = ( 
                "^/midcom-static/.*" => "$0",
                "^(.*)\.*" => "midcom-root-midgard2.php" 
            )

* Configure PHP accordingly:

        extension=midgard2.so
        midgard.configuration="myconfigfile"
        midgard.http="on"
    
* Play around
* More info in <http://blogs.nemein.com/people/piotras/view/1208851555.html>

Troubleshooting
---------------

### Installing PHPTAL

MidCOM3 requires TAL to be installed. To get it, run:

    # pear install http://phptal.motion-twin.com/latest.tar.gz

### Installing Syck

Syck depends on PHP 5.2, but works also on PHP 5.1 with a bit of hacking

    # apt-get install php5-syck
    
Alternatively add `deb http://packages.dotdeb.org etch all` and `deb-src http://packages.dotdeb.org etch all` to `/etc/apt/sources.list` and run

    # apt-get update
    # apt-get upgrade

### Installing GIT

1. Install GIT with `# apt-get install git-core`
2. Get the GIT repository `# git clone git://repo.or.cz/midcom.git`
3. Point the `MidgardRootFile` of the Midgard Apache VirtualHost setup to MidCOM's `httpd/midcom-root.php`

### Installing Phing and MidCOM statics

    # pear channel-discover pear.phing.info
    # pear install phing/phing
    # phing linkstatics -Dstatic_dir=/usr/share/php/midcom/static

### Installing Markdown

    # pear channel-discover pear.michelf.com
    # pear install pear.michelf.com/markdown