/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

(function($){
    
    $.midgardmvc = {
        _inited: false,
        config: {
            MIDGARDMVC_STATIC_URL: '/midcom-static',
            MIDGARDMVC_PAGE_PREFIX: '/',
            enable_watchers: false,
            debug: false
        },
        registered_components: {}
    };
    $.extend($.midgardmvc, {
        init: function(config) {
            $.midgardmvc.config = $.extend({}, $.midgardmvc.config, config || {});
            $.midgardmvc.registered_components = {};
            
            $.midgardmvc.logger.init($.midgardmvc.config.force_static_logger || true);
            
            if ($.midgardmvc.config.enable_watchers) {
                $.midgardmvc.watcher.init();
            }
            
            $.midgardmvc._inited = true;

            $.midgardmvc.logger.log('jsmidgardmvc inited with config ' + $.midgardmvc.helpers.pretty_print($.midgardmvc.config));
            
            // setTimeout("jQuery.midcom.logger.debug('this is debug message')", 1000);
            // setTimeout("jQuery.midcom.logger.warning('this is warning message')", 2000);
            // setTimeout("jQuery.midcom.logger.error('this is error message')", 3000);
            
            $.midgardmvc.events.signals.trigger('midcom::init-ready');
        },
        update_config: function(config) {            
            $.midgardmvc.config = $.extend({}, $.midgardmvc.config, config || {});
            $.midgardmvc.logger.log('midcom config updated to ' + $.midgardmvc.helpers.pretty_print($.midgardmvc.config));
        },
        register_component: function(name, handler) {
            if (typeof $.midgardmvc.registered_components[name] == 'undefined') {
                if (! $.midgardmvc._inited) {
                    $.midgardmvc.events.signals.listen('midcom::init-ready', $.midgardmvc._register_component, [name, handler], false);
                } else {
                    $.midgardmvc._register_component(name, handler);
                }
            }
        },
        _register_component: function(name, handler) {
            $.midgardmvc.logger.log('registering component: '+name);
            
            $.midgardmvc.registered_components[name] = handler;
        }
    });
    
    $.midgardmvc.events = {};
    $.midgardmvc.events.signals = {
        _listeners: null,
        trigger: function(signal, data) {
            $.midgardmvc.logger.debug('midcom.events.signals::trigger "'+signal+'" data: ' + $.midgardmvc.helpers.pretty_print(data));
            
            if (   $.midgardmvc.events.signals._listeners === null
                || typeof $.midgardmvc.events.signals._listeners[signal] == 'undefined')
            {
                return;
            }
            
            if (   typeof data == 'undefined'
                || typeof data != 'object')
            {
                var data = [];
            }
            
            $.each($.midgardmvc.events.signals._listeners[signal], function(i,listener){
                if (typeof listener != 'object') {
                    return;
                }
                
                if (typeof listener.func == 'function') {
                    var args = data;
                    if (   typeof listener.args != 'undefined'
                        && listener.args != null)
                    {
                        $.each(listener.args, function(i,a){
                            args.push(a);
                        });
                    }
                    
                    listener.func.apply(listener.func, args);
                    
                    if (! listener.keep) {
                        $.midgardmvc.events.signals._listeners[signal][i] = null;
                    }
                }
            });
        },
        listen: function(signal, listener, data, keep) {
            if ($.midgardmvc.events.signals._listeners === null) {
                $.midgardmvc.events.signals._listeners = {};
            }
            if (typeof $.midgardmvc.events.signals._listeners[signal] == 'undefined') {
                $.midgardmvc.events.signals._listeners[signal] = [];
            }
            
            if (typeof keep == 'undefined') {
                var keep = true;
            }
            
            var lstnr = {
                func: listener,
                args: data,
                keep: keep
            };
            
            $.midgardmvc.events.signals._listeners[signal].push(lstnr);
        }
    };
    
    $.midgardmvc.logger = {
        row_num: 1,
        force_static: false,
        _static_holder: null,        
        _out: function(msg, type) {
            if (typeof type == 'undefined') {
                var type = 'log';
            }
            
            if (   typeof window['console'] == 'undefined'
                || $.midgardmvc.logger.force_static)
            {
                if (typeof msg != 'string') {
                    msg = $.midgardmvc.helpers.pretty_print(msg);
                }

                msg = $.midgardmvc.logger.row_num + ": " + msg;
                $.midgardmvc.logger.row_num += 1;
                
                $.midgardmvc.logger._static(msg, type);
            }
            else
            {
                $.midgardmvc.logger._console(msg, type);
            }
        },
        _console: function(msg, type) {
            if (! $.midgardmvc.config.debug) {
                return;
            }
            
            if (typeof console[type] != 'undefined') {
                console[type](msg);
            } else {
                console.log(msg);
            }
        },
        _static: function(msg, type) {
            if ($.midgardmvc.logger._static_holder == null) {
                $.midgardmvc.logger._generate_static();
            }
            
            $.midgardmvc.logger._static_holder.trigger('add_message', [msg, type]);
        },
        _generate_static: function() {            
            $.midgardmvc.logger._static_holder = $('<div id="jsmidgardmvc_logger" />').hide();
            $.midgardmvc.logger._static_holder.appendTo('body');
            
            if ($.midgardmvc.config.debug) {
                $.midgardmvc.logger._static_holder.show();
            }
            
            var header = $('<div class="jsmidgardmvc_logger_header" />').html('Logger');
            header.appendTo($.midgardmvc.logger._static_holder);
            
            var messages_visible = true;
            header.bind('click', function(e){
                if (messages_visible) {
                    messages_visible = false;
                    messages.hide();
                } else {
                    messages_visible = true;
                    messages.show();
                }
            });

            var messages = $('<div class="jsmidgardmvc_logger_messages" />');
            messages.appendTo($.midgardmvc.logger._static_holder);
            
            $.midgardmvc.logger._static_holder.bind('add_message', function(evt, msg, type){
                var message = $('<div class="jsmidgardmvc_logger_message" />')
                .hide()
                .addClass(
                    'jsmidgardmvc_logger_message_' + type
                ).html(
                    msg
                );
                message.prependTo(messages);
                
                message.fadeIn('normal');
            });
        }
    };
    $.extend($.midgardmvc.logger, {
        init: function(force_static) {
            if (   typeof force_static
                && force_static)
            {
                $.midgardmvc.logger.force_static = true;
            }
        },
        log: function(msg) {
            $.midgardmvc.logger._out(msg, 'log');
        },
        debug: function(msg) {
            $.midgardmvc.logger._out(msg, 'debug');
        },
        warning: function(msg) {
            $.midgardmvc.logger._out(msg, 'warning');
        },
        error: function(msg) {
            $.midgardmvc.logger._out(msg, 'error');
        }
    });
    
    $.midgardmvc.watcher = {
        targets: []
    };
    $.extend($.midgardmvc.watcher, {
        init: function() {
            $.midgardmvc.watcher.targets = [];
        },
        register: function(url, callback) {
            
        }
    });
    
    $.midgardmvc.dispatcher = {};    
    $.extend($.midgardmvc.dispatcher, {
        get: function(url, on_success, on_error) {
            var return_data = null;
            if (typeof on_success == 'undefined') {
                alert('No on_success method given for dispatcher');
                return;
            }
            if (typeof on_error == 'undefined') {
                var on_error = $.midgardmvc.dispatcher._on_error;
            }
            
            $.ajax({
                url: url,
                type: "GET",
                global: false,
                cache: true,
                async: false,
                dataType: "xml",
                error: function(req) {
                    on_error(url, req);
                },
                success: function(data) {
                    if (typeof on_data == 'function') {
                        on_success(data, url);
                        return;
                    }
                    return_data = data;
                }
            });
            
            return return_data;
        },
        _on_success: function(url, req) {
            alert("Error retrieving data from "+url+" reason: "+req.responseText);
        }
    });
    
    $.midgardmvc.utils = {};

    $.midgardmvc.utils.load_script = function(url, callback, callback_args) {
        $('head').append('<script type="text/javascript" charset="utf-8" src="'+url+'"></script>');
        if (typeof callback == 'string') {
            if (   typeof callback_args == 'undefined'
                || typeof callback_args != 'object')
            {
                var callback_args = [];
            }
            
            setTimeout('eval("var fn = eval('+callback+'); fn.apply(fn, [\''+callback_args.join("','")+'\']);")', 300);
        }
    };
    
    $.midgardmvc.helpers = {};
    
    $.midgardmvc.helpers.generate_id = function(prefix) {
        if (typeof prefix == 'undefined') {
            var prefix = '';
        }
        
        var date = new Date();
        var random_key = Math.floor(Math.random()*4013);
        var random_key2 = Math.floor(Math.random()*3104);

        return prefix + (Math.floor(((date.getTime()/1000)+random_key2) + (10016486 + (random_key * 22423)) * random_key / random_key2).toString()).toString().substr(0,8);
    };
    
    $.midgardmvc.helpers.clone = function(obj) {
        if (   obj == null
            || typeof(obj) != 'object')
        {
            if (obj == null) {
                return null;
            } else if (typeof obj == 'string') {
                return '' + obj;
            }
            
            return obj;
        }
        
        var temp = {};
        for (var key in obj) {
            if (typeof obj[key] == 'object') {
                temp[key] = $.midgardmvc.helpers.clone(obj[key]);
            } else {
                temp[key] = obj[key];
            }            
        }

        return temp;        
    };
    
    $.midgardmvc.helpers.is_a = function(source, constructor) {
        while (source != null) {
            if (source == constructor.prototype) {
                return true;
            }
            source = source.__proto__;
        }
        return false;
    };
    
    $.midgardmvc.helpers.is_null = function(item) {        
        if (   typeof item != 'undefined'
            && item == null)
        {
            return true;
        }
        
        return typeof item == 'object' && !item;
    };
    
    /**
     * uses xmlObjectifier from http://www.terracoder.com/
     */
    $.midgardmvc.helpers.xml = {
        utils_loaded: false,
        is_utils_loaded: function() {
            if (   $.midgardmvc.helpers.xml.utils_loaded
                && typeof $.xmlToJSON != 'undefined')
            {
                return true;
            }
            
            return false;
        },
        load_xml_utils: function(callback, callback_args) {
            if ($.midgardmvc.helpers.xml.is_utils_loaded()) {
                return;
            }
            
            var url = $.midgardmvc.config.MIDGARDMVC_STATIC_URL + '/midgardmvc_core/jQuery/jqXMLUtils.js';
            $.midgardmvc.utils.load_script(url, callback, callback_args);
            
            $.midgardmvc.helpers.xml.utils_loaded = true;
        }
    };
    $.extend($.midgardmvc.helpers.xml, {
        to_JSON: function(data) {
            if (! $.midgardmvc.helpers.xml.is_utils_loaded()) {
                var callback = "jQuery.midcom.helpers.xml.to_JSON";
                
                $.midgardmvc.helpers.xml.load_xml_utils(callback, [data]);
                
                return;
            }
            
            return $.xmlToJSON(data);
        },
        from_text: function(text) {
            if (! $.midgardmvc.helpers.xml.is_utils_loaded()) {
                var callback = "jQuery.midcom.helpers.xml.from_text";
                
                $.midgardmvc.helpers.xml.load_xml_utils(callback, [text]);
                
                return;
            }
            
            return $.textToXML(text);
        }
    });
    
    $.midgardmvc.helpers.json = {};
    $.extend($.midgardmvc.helpers.json, {
        /**
         * Parses and evaluates JSON string to Javascript
         * @param {String} json_str JSON String
         * @returns Parsed JSON string or false on failure
         */
        parse: function (json_str) {
        	try {
        	    var re = new RegExp('[^,:{}\[\]0-9.\-+Eaeflnr-u \n\r\t]');
                return !(re.test(json_str.replace(/"(\\.|[^"\\])*"/g, ''))) && eval('(' + json_str + ')');
            } catch (e) {
                return false;
            }
        },

        /**
         * Parses Javascript to JSON
         * @param {Mixed} item Javascript to be parsed
         * @param {String} type of the Javascript item to be parsed (Optional)
         * @returns JSON string
         * @type String
         */
        convert:  function(item, item_type) {
            var m = {
                '\b': '\\b',
                '\t': '\\t',
                '\n': '\\n',
                '\f': '\\f',
                '\r': '\\r',
                '"' : '\\"',
                '\\': '\\\\'
            },
            s = {
                arr: function (x) {
                    var a = ['['], b, f, i, l = x.length, v;
                    for (i = 0; i < l; i += 1) {
                        v = x[i];
                        v = conv(v);
                        if (typeof v == 'string') {
                            if (b) {
                                a[a.length] = ',';
                            }
                            a[a.length] = v;
                            b = true;
                        }
                    }
                    a[a.length] = ']';
                    return a.join('');
                },
                bool: function (x) {
                    return String(x);
                },
                nul: function (x) {
                    return "null";
                },
                num: function (x) {
                    return isFinite(x) ? String(x) : 'null';
                },
                obj: function (x) {
                    if (x) {
                        if (x instanceof Array) {
                            return s.arr(x);
                        }
                        var a = ['{'], b, f, i, v;
                        for (i in x) {
                            v = x[i];
                            v = conv(v);
                            if (typeof v == 'string') {
                                if (b) {
                                    a[a.length] = ',';
                                }
                                a.push(s.str(i), ':', v);
                                b = true;
                            }
                        }
                        a[a.length] = '}';
                        return a.join('');
                    }
                    return 'null';
                },
                str: function (x) {
                    if (/["\\\x00-\x1f]/.test(x)) {
                        x = x.replace(/([\x00-\x1f\\"])/g, function(a, b) {
                            var c = m[b];
                            if (c) {
                                return c;
                            }
                            c = b.charCodeAt();
                            return '\\u00' +
                                Math.floor(c / 16).toString(16) +
                                (c % 16).toString(16);
                        });
                    }
                    return '"' + x + '"';
                }
            };
            conv = function (x) {
                switch(typeof x) {
                    case "object":
                        if ($.midgardmvc.helpers.is_a(x, Array)) {
                            return s.arr(x);
                        } else {
                            return s.obj(x);
                        }         
                    break;
                    case "string":
                        return s.str(x);
                    break;
                    case "number":
                        return s.num(x);
                    break;
                    case "null":
                        return s.nul(x);
                    break;
                    case "boolean":
                        return s.bool(x);
                    break;
                }
            }

            var itemtype = item_type || typeof item;
            switch (itemtype) {
                case "object":
                    if ($.midgardmvc.helpers.is_a(item, Array)) {
                        return s.arr(item);
                    } else {
                        return s.obj(item);
                    }         
                break;
                case "string":
                    return s.str(item);
                break;
                case "number":
                    return s.num(item);
                break;
                case "null":
                    return s.nul(item);
                break;
                case "boolean":
                    return s.bool(item);
                break;
                default:
                    throw ("Unknown type for $.midgardmvc.helpers.json.convert");
            }            
        }
    });
    
    /**
     * Renders pretty printed version from given value
     * Original pretty_print function by Damien Katz <damien_katz@yahoo.com>
     * Modified to work with Ajatus and jsMidCOM by Jerry Jalava <jerry.jalava@gmail.com>     
     * @param {Mixed} val Value to render
     * @param {Number} indent Current indent level (Default: 4)
     * @param {String} linesep Line break to be used (Default: "\n")
     * @param {Number} depth Current line depth (Default: 1)
     * @returns Pretty printed value
     * @type String
     */
    $.midgardmvc.helpers.pretty_print = function(val, indent, indent_char, linesep, depth) {
        var indent = typeof indent != 'undefined' ? indent : 6;
        var indent_char = typeof indent_char != 'undefined' ? indent_char : '&nbsp;';
        var linesep = typeof linesep != 'undefined' ? linesep : "<br/>";
        var depth = typeof depth != 'undefined' ? depth : 1;
        
        var propsep = linesep.length ? "," + linesep : ", ";

        var tab = [];

        for (var i = 0; i < indent * depth; i++) {
            tab.push("");
        };
        tab = tab.join(indent_char);

        switch (typeof val) {
            case "boolean":
            case "number":
            case "string":
                return $.midgardmvc.helpers.json.convert(val);
            case "object":
                if (val === null) {
                    return "null";
                }
                if (val.constructor == Date) {
                    return $.midgardmvc.helpers.json.convert(val);
                }

                var buf = [];
                if (val.constructor == Array) {
                    buf.push("[");
                    for (var index = 0; index < val.length; index++) {
                        buf.push(index > 0 ? propsep : linesep);
                        buf.push(
                            tab, $.midgardmvc.helpers.pretty_print(val[index], indent, indent_char, linesep, depth + 1)
                        );
                    }
                    
                    if (index >= 0) {
                        buf.push(linesep, tab.substr(indent))
                    };
                    
                    buf.push("]");
                } else {
                    buf.push("{");
                    var index = 0;
                    for (var key in val) {                        
                        if (! val.hasOwnProperty(key)) {
                            continue;
                        };
                        
                        buf.push(index > 0 ? propsep : linesep);
                        buf.push(
                            tab, $.midgardmvc.helpers.json.convert(key), ": ",
                            $.midgardmvc.helpers.pretty_print(val[key], indent, indent_char, linesep, depth + 1)
                        );
                        index++;
                    }
                    
                    if (index >= 0) {
                        buf.push(linesep, tab.substr(indent));
                    };
                    
                    buf.push("}");
                }
                
                return buf.join("");
            break;
        }
    };

    $.midgardmvc.helpers.comet = {
        backend: 'midcom',
        backends: {},
        _backend_loaded: false,
        _callbacks: {},
        _cb_set: false,
        is_backend_loaded: function() {
            if ($.midgardmvc.helpers.comet._backend_loaded) {
                return true;
            }
            
            if (typeof $.midgardmvc.helpers.comet.backends[$.midgardmvc.helpers.comet.backend] == 'undefined') {
                return false;
            }
            
            return $.midgardmvc.helpers.comet.backends[$.midgardmvc.helpers.comet.backend].available();
            
            return false;
        },
        load_backend: function(callback, callback_args) {            
            if ($.midgardmvc.helpers.comet.is_backend_loaded()) {
                if (typeof callback == 'string') {
                    if (   typeof callback_args == 'undefined'
                        || typeof callback_args != 'object')
                    {
                        var callback_args = [];
                    }

                    setTimeout('eval("var fn = eval('+callback+'); fn.apply(fn, [\''+callback_args.join("','")+'\']);")', 200);
                }
            }
            
            $.midgardmvc.helpers.comet._backend_loaded = $.midgardmvc.helpers.comet.backends[$.midgardmvc.helpers.comet.backend]._load(callback, callback_args);
        }
    };
    $.extend($.midgardmvc.helpers.comet.backends, {
        pi: {
            available: function() {
                if (typeof pi != 'undefined') {
                    return true;
                }
                return false;
            },
            launch: function(type, url, callback, send_type, data) {
            	switch (type) {
            	    case 'send':
            			var tunnel = new pi.xhr;
            			tunnel.environment.setType(send_type.toString().toUpperCase());
            			tunnel.environment.setUrl(url);

            			if (typeof data == 'object') {
            			    $.each(data, function(i,n){
            			        tunnel.environment.addData(i, n);
            			    });
            			}

            			tunnel.send();

            			return tunnel;
            	    break;
            	    case 'listen':
            	    default:
            	        req = new pi.comet();
                    	req.environment.setUrl(url);
                    	if (! $.midgardmvc.helpers.comet._cb_set)
                    	{
                        	req.event.push = callback(resp);
                        	$.midgardmvc.helpers.comet._cb_set = true;
                    	}
                    	req.send();

                    	return req;
            	    break;
            	}
            },
            _load: function(callback, callback_args) {
                var url = $.midgardmvc.config.MIDGARDMVC_STATIC_URL + '/midgardmvc_core/pi.js';
                $.midgardmvc.utils.load_script(url, callback, callback_args);
                
                return true;
            }
        },
        midcom: {
            available: function() {
                if (typeof $.midgardmvc.helpers.cometclass != 'undefined') {
                    return true;
                }
                return false;
            },
            launch: function(type, url, callback, send_type, data) {
                
                if (typeof $.midgardmvc.helpers.cometclass[type] == 'undefined') {
                    return false;
                }
                
                var tunnel = new $.midgardmvc.helpers.cometclass[type]();
                tunnel.env.setUrl(url);
                
                switch (type) {
                    case 'send':
                        tunnel.env.setType(send_type.toString().toUpperCase());                        
            			if (typeof data == 'object') {
            			    $.each(data, function(i,n){
            			        tunnel.env.addData(i, n);
            			    });
            			}
                    break;
                    case 'listen':
                    	tunnel.events.push = function(resp) {
                    	    callback(resp);
                    	};
                    break;
                }

    			tunnel.send();
    			
    			return tunnel;
            },
            _load: function(callback, callback_args) {
                var url = $.midgardmvc.config.MIDGARDMVC_STATIC_URL + '/midgardmvc_core/helpers/comet.js';
                $.midgardmvc.utils.load_script(url, callback, callback_args);
            }
        }
    });
    $.extend($.midgardmvc.helpers.comet, {
        start: function() {            
            if (typeof $.midgardmvc.helpers.comet.backends[$.midgardmvc.helpers.comet.backend] == 'undefined') {
                return false;
            }
            
            if (arguments.length < 2) {
                return false;
            }
            
            var type = 'listen';

            var url = arguments[0];
            
            var cb = false;
            var send_type = null;
            var data = null;
            
            if (arguments.length == 3) {
                type = 'send';
                
                send_type = arguments[1];
                data = arguments[2];
            } else {
                cb = arguments[1];
            }
            
            var callback_id = false;
            if (cb) {
                callback_id = $.midgardmvc.helpers.comet._register_callback(cb);
            }
            
            if (! $.midgardmvc.helpers.comet.is_backend_loaded()) {
                var callback = "jQuery.midcom.helpers.comet._launch_comet";
                var args = [type, url, callback_id, send_type, data];
                $.midgardmvc.helpers.comet.load_backend(callback, args);
            } else {
                return $.midgardmvc.helpers.comet._launch_comet(type, url, callback_id, send_type, data);
            }
        },
        _register_callback: function(callback) {            
            var id = $.midgardmvc.helpers.generate_id();
            
            $.midgardmvc.helpers.comet._callbacks[id] = callback;
            
            return id;
        },
        _launch_comet: function(type, url, callback_id, send_type, data) {
            var callback = function(r){};
            if (typeof $.midgardmvc.helpers.comet._callbacks[callback_id] != 'undefined') {
                callback = $.midgardmvc.helpers.comet._callbacks[callback_id];
            }
            
            $.midgardmvc.helpers.comet.backends[$.midgardmvc.helpers.comet.backend].launch(type, url, callback, send_type, data);
        	
        	return false;
        }
    });
    
    $.midgardmvc.services = {};
    
    $.midgardmvc.services.configuration = {        
        merge: function(a,b) {
            var c = {};
            
            if (typeof a == 'undefined') {
                return c;
            }        
            if (   typeof b == 'undefined'
                || typeof b != 'object')
            {
                var b = {};
            }

            for (var ak in a) {                
                if (   typeof a[ak] != 'object'
                    || $.midgardmvc.helpers.is_null(a[ak]))
                {
                    c[ak] = a[ak];
                    if (   typeof b[ak] != 'undefined'
                        && typeof b[ak] != 'object')
                    {
                        c[ak] = b[ak];
                    }
                } else {
                    if (typeof b[ak] == 'undefined') {
                        c[ak] = $.midgardmvc.services.configuration.merge(a[ak], {});
                    } else {
                        c[ak] = $.midgardmvc.services.configuration.merge(a[ak], b[ak]);
                    }                
                }
            }

            return c;
        }
    };
    
    $.midgardmvc.storage = {};
    
    $.midgardmvc.storage.cookies = function() {        
        var config = {};
        this.namespace = null;
        
        if (typeof arguments[0] == 'string') {
            config['name'] = arguments[0];
        }
        if (typeof arguments[0] == 'object') {
            config = arguments[0];
        }
        if (   arguments.length == 2
            && typeof arguments[0] == 'string'
            && typeof arguments[1] == 'object')
        {
            config['name'] = arguments[0];
            config = arguments[1];
        }

        this.enabled = false;
        this.config = $.midgardmvc.services.configuration.merge({
                name: 'midcom.storage.cookie',
                expires: null,
                domain: null,
                path: null,
                secure: false
            },
            config
        );
        
        var expdate = new Date();
        if (!$.midgardmvc.helpers.is_null(this.config.expires)) {
            if ($.midgardmvc.helpers.is_a(this.config.expires, Date)) {
                expdate = this.config.expires;
            } else {
                exp_seconds = this.config.expires;
                expdate.setTime(expdate.getTime() + (exp_seconds * 1000));
            }
            this.expires = expdate;
        } else {
            expdate.setTime(expdate.getTime() + ((24 * 60 * 60) * 1000));
            this.expires = expdate;
        }
        
        if (this.config.domain == '__auto__') {
            this.config.domain = document.domain;
        }

        this._checkSupport();
        
        if (this.enabled) {
            if (   document.cookie == ""
                || !this.exists(this.config.name))
            {
                this.save("initialized", 1, "parameters");
            }
        }
    };
    $.extend($.midgardmvc.storage.cookies.prototype, {
        exists: function(key) {
            if (   typeof key == 'undefined'
                || key == ''
                || key == null)
            {
                key = this.config.name;
            }
            key = key.replace(/\./g, '_');
            
            if (   !document.cookie
                || document.cookie == '')
            {
                return false;
            }

            var start = document.cookie.indexOf(key+"=");

            if (start == -1) {
                return false;
            }

            if (   !start
                && (key != document.cookie.substring(0,key.length)))
            {
                return false;
            }
            
            return true;
        },
        save: function(key, value, section) {
            $.midgardmvc.logger.debug("cookie save key:"+key+", value: "+$.midgardmvc.helpers.pretty_print(value)+", section: "+section);
            
            if (   typeof key == 'undefined'
                || typeof value == 'undefined')
            {
                return null;
            }
            key = key.replace(/\./g, '_');
            
            if (   typeof section == 'undefined'
                || section == ''
                || section == null)
            {
                var section = "data";
            }

            this._updateSection(section, key, $.midgardmvc.helpers.json.convert(value));
        },
        read: function(key, section) {
            $.midgardmvc.logger.debug("cookie read key:"+key+" section: "+section);
            
            if (typeof key == 'undefined')
            {
                return null;
            }
            key = key.replace(/\./g, '_');
            
            if (   typeof section == 'undefined'
                || section == ''
                || section == null)
            {
                var section = "data";
            }
            
            var rawdata = this._readSection(section);
            if (! rawdata) {
                return null;
            }

            var splitted_raw = rawdata.split("|");

            for (var i=0; i<splitted_raw.length; i++) {
                var datarow = splitted_raw[i];
                if (key == datarow.split("#")[0]) {
                    return $.midgardmvc.helpers.json.parse(datarow.split("#")[1]);
                }
            }
            
            return null;
        },
        remove: function() {
            this._removeCookie();
        },
        
        _sectionExists: function(section) {
            var memdata = this._readCookie();
            if (! memdata) {
                return false;
            }
            
            var parsed = memdata.split("!");
            
            if (   typeof section == 'undefined'
                || section == ''
                || section == null)
            {                
                return false;
            }
            
            for (var i=0; i<parsed.length; i++) {
                if (section == parsed[i].split("=")[0]) {
                    console.log("section "+section+" exists");
                    return true;
                }
            }
            
            return false;
        },
        _readSection: function(section, others_only) {            
            if (typeof others_only == 'undefined') {
                var others_only = false;
            }
            
            if (   typeof section == 'undefined'
                || section == ''
                || section == null)
            {                
                return "";
            }

            var memdata = this._readCookie();
            if (! memdata) {
                return "";
            }

            var parsed = memdata.split(":!:");

            if (others_only) {
                var section_str = "";
                for (var i=0; i<parsed.length; i++) {
                    if (section != parsed[i].split("=")[0]) {
                        if (i != parsed.length-1) {
                            section_str = section_str + parsed[i] + ":!:";
                        } else {
                            section_str = section_str + parsed[i];
                        }
                    }
                }
                return section_str;
            } else {
                for (var i=0; i<parsed.length; i++) {
                    if (section == parsed[i].split("=")[0]) {
                        var value_str = parsed[i].split("=")[1];
                        return value_str;
                    }
                }
            }
        },
        _updateSection: function(section, key, value) {
            $.midgardmvc.logger.debug("_updateSection key:"+key+" value: "+value+" section: "+section);
            
            if (   typeof key == 'undefined'
                || typeof value == 'undefined'
                || typeof section == 'undefined')
            {
                return null;
            }
            key = key.replace(/\./g, '_');

            var section_data = this._readSection( section );

            if (section_data) {
                var splitted_sd = section_data.split("|");

                var ssdlen = splitted_sd.length;
                
                var key_updated = false;
                var new_sd = new Array();
                
                for (var i=0; i<splitted_sd.length; i++) {
                    var tmpkey = splitted_sd[i].split("#")[0];
                    var tmpval = splitted_sd[i].split("#")[1];
                    if (key == tmpkey) {
                        var tmparr = [ tmpkey, value ];
                        new_sd[i] = tmparr.join("#");
                        key_updated = true;
                    } else {
                        var tmparr = [ tmpkey, tmpval ];
                        new_sd[i] = tmparr.join("#");
                    }
                }

                if (! key_updated) {
                    var newdata = key + "#" + value;
                    new_sd[sdlen] = newdata;
                }

                var new_sd_str = new_sd.join("|");
                
                var other_sections = this._readSection( section, true );
                if (other_sections) {
                    this._writeCookie(section + "=" + new_sd_str+":!:"+other_sections);
                } else {
                    this._writeCookie(section + "=" + new_sd_str);
                }
            } else {
                var new_data = new Array();
                new_data[0] = key + "#" + value;

                var section_str = section + "=" + new_data.join("|");

                var other_sections = this._readSection( section, true );
                if (other_sections) {
                    this._writeCookie(section_str + ":!:" + other_sections);
                } else {
                    this._writeCookie(section_str);
                }
            }
        },
        
        _readCookie: function( name ) {
            if (   typeof name == 'undefined'
                || name == ''
                || name == null)
            {
                name = this.config.name;
            }
            name = name.replace(/\./g, '_');
            
            $.midgardmvc.logger.debug("_readcookie cookie: "+document.cookie);
            
            var start = document.cookie.indexOf(name+"=");
            var len = start + name.length + 1;

            if (start == -1) {
                return null;
            }

            if (   !start
                && (name != document.cookie.substring(0,name.length)))
            {
                return null;
            }

            var end = document.cookie.indexOf(";",len);
            end = (end == -1 ? document.cookie.length : end);

            return this._normalize(document.cookie.substring(len,end));
        },
        _writeCookie: function(value, name, path, domain, secure, expires) {            
            if (   typeof expires == 'undefined'
                || expires == ''
                || expires == null)
            {
                var expires = this.expires;
            }
            
            if (   typeof name == 'undefined'
                || name == ''
                || name == null)
            {
                name = this.config.name;
            }
            name = name.replace(/\./g, '_');

            var path = (typeof path != 'undefined') ? path : this.config.path;
            var domain = (typeof domain != 'undefined') ? domain: this.config.domain;
            var secure = (typeof secure != 'undefined') ? secure: this.config.secure;
            
            var cookie_str = name + "=" + this._serialize(value) +
            ( ";expires=" + expires.toGMTString() ) +
            ( (!$.midgardmvc.helpers.is_null(path)) ? ";path=" + path : "" ) +
            ( (!$.midgardmvc.helpers.is_null(domain)) ? ";domain=" + domain : "") +
            ( (secure == true) ? ";secure" : "");

            $.midgardmvc.logger.debug("_writeCookie cookie_str: "+cookie_str);
            
            if (! this.exists(name)) {
                document.cookie = cookie_str;
            } else {
                document.cookie = cookie_str;
            }
        },
        _removeCookie: function(name, path, domain) {
            if (   typeof name == 'undefined'
                || name == ''
                || name == null)
            {
                name = this.config.name;
            }
            name = name.replace(/\./g, '_');
                        
            var path = (typeof path != 'undefined') ? path : this.config.path;
            var domain = (typeof domain != 'undefined') ? domain: this.config.domain;

            document.cookie = name + "=" +
            ";expires=-1" + //"Thu, 01-Jan-70 00:00:01 GMT" +
            ( (!$.midgardmvc.helpers.is_null(path)) ? ";path=" + path : "") +
            ( (!$.midgardmvc.helpers.is_null(domain)) ? ";domain=" + domain : "");
        },
        _checkSupport: function() {
            var cookieEnabled = false;

            if (typeof navigator.cookieEnabled == 'undefined') {
                if (this.exists("midcom.storage.cookie.test")) {
                    cookieEnabled = true;
                } else {
                    this._writeCookie("1", "midcom.storage.cookie.test", null, null);

                    cookieEnabled = this.exists("midcom.storage.cookie.test");

                    this._removeCookie( "midcom.storage.cookie.test", null, null );                    
                }
            } else {
                cookieEnabled = (navigator.cookieEnabled) ? true : false;
            }

            if (cookieEnabled) {
                this.enabled = true;
            }
        },        
        _normalize: function(string) {
            return decodeURIComponent(string);
        },
        _serialize: function(string) {
            return encodeURIComponent(string);
        }
    });
    
    /**
     * Javascript extensions
    **/
    
    /**
     * Does variable substitution on the string with given parameters
     *
     * Example:
     * var params = {proto: 'http://', domain: 'midgard-project.com', sub: 'www'};
     * var url = "{proto}{sub}.{domain}/index.html".supplant(params);
    **/
    String.prototype.supplant = function (o) {
        return this.replace(/{([^{}]*)}/g,
            function (a, b) {
                var r = o[b];
                return typeof r === 'string' || typeof r === 'number' ? r : a;
            }
        );
    };
    
    /**
     * Does variable substitution on the string with given parameters
     *
     * Example:
     * var arg1 = 'http://', arg2 = 'midgard-project.com', arg3: 'www';
     * var url = "{0}{1}.{2}/index.html".format(arg1, arg2, arg3);
    **/
    String.prototype.format = function(){
		var values = arguments;
		return this.replace(/\{(\d)\}/g, function() {
			return values[arguments[1]];
		});
	};
    
})(jQuery);