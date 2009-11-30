/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

(function($){
    
    $.midgardmvc = $.midgardmvc || {};
    $.midgardmvc.helpers = $.midgardmvc.helpers || {};

    $.midgardmvc.helpers._cometclass = {
        _env: {
            _getsetters: ['api', 'callback', 'header', 'data', 'type', 'async', 'url', 'cache', 'mimeType', 'channel', 'multipart', 'timeout', 'name', 'tunnel'],
            _constructor: {
                api: null,
                callback: [],
                header: {},
                data: {},
                type: 'GET',                                                                
                async: true,
                url: '',
                cache: true,
                mimeType: null,                                                                
                channel: null,
                multipart: false,
                timeout: 0,
                name: '',
                tunnel: null,
                _parent_: null,
                
        		addCallback: function(funct, rs_val, stat_val) {
        		    if (! $.midgardmvc.helpers.is_a(this.getCallback(), Array)) {
        		        this.callback = [];
        		    }
        			this.getCallback().push({
        			    fn: funct,
        			    readyState: rs_val || 4,
        			    status: stat_val || 200
        			});
        		},
        		addHeader: function(key, val) {
        			this.getHeader()[key] = val;
        		},
        		addData: function(key, val) {
        			this.getData()[key] = val;
        		},
        		setCache: function(val) {
        			if (val == false) {
        				this.addData("forceCache", Math.round(Math.random()*10000));
        			}
        			this.cache = val;
        		},
        		setType: function(val) {
        			if (val == "POST") {
        				this.addHeader("Content-Type","application/x-www-form-urlencoded");
        			}
        			this.type = val;
        		},
        		setTunnel: function(val) {
        			if (this.getType() == 1) {
        				val.env.addData('cometType', '1');
        				val.env.addCallback(this._parent_.events.change, 3);
        				val.env.setCache(false);
        			}

                    val._cometApi_ = this._parent_;
        			this.tunnel = val;
        		}
            },
            generate: function(parent) {
                var inst = $.midgardmvc.helpers.clone($.midgardmvc.helpers._cometclass._env._constructor);
                inst._parent_ = parent;
                
                for (var key in $.midgardmvc.helpers._cometclass._env._getsetters) {
                    var item = $.midgardmvc.helpers._cometclass._env._getsetters[key];
                    var name = item, title = name.substring(0, 1).toUpperCase()+name.substring(1);
                    
                    if (Boolean(inst['get' + title]) == false) {
                        inst['get' + title] = new Function('return this.' + name);
                    }
                    
                    if (Boolean(inst['set' + title]) == false) {
                        inst['set' + title] = new Function('val','this.' + name + ' = val;');
                    }
                }
                
                return inst;
            }
        },
        _xhr: function() {
            var api = window.XMLHttpRequest ? XMLHttpRequest : ActiveXObject("Microsoft.XMLHTTP");
            var _self = this;
                        
            this.env = $.midgardmvc.helpers._cometclass._env.generate(this);
            
            this.events = {
                readystatechange: function() {
        			var ready_state = _self.env.getApi().readyState;
        			var callback = _self.env.getCallback();
                    for (var i = 0; i < callback.length; i++) {
                        if (callback[i].readyState == ready_state) {
                            callback[i].fn.apply(callback[i].fn, [_self]);
                        }
                    }
        		},
        		error: function() {
        		}
            };
            
            this.actions = {
        		abort: function() {
        			this.env.getApi().abort();
        		},
        		send: function() {
        			var url = this.env.getUrl(), data = this.env.getData(),dataUrl = ""; 

        			for (key in data) {
        			    dataUrl += "{0}={1}&".format(key, data[key]);
        			}

        			if (this.env.getType()=="GET"&&url.search("\\?") == -1) {
        			    url += "?{0}".format(dataUrl);
        			}

        			this.env.getApi().open(this.env.getType(), url, this.env.getAsync());

        			for (key in this.env.getHeader()) {
        			    this.env.getApi().setRequestHeader(key, this.env.getHeader()[key]);
        			}

        			this.env.getApi().send(this.env.getType() == "GET" ? "" : dataUrl);
        		}
            };
            
            this.env.setApi( new api() );

            this.env.getApi().onreadystatechange = this.events.readystatechange;
    		this.env.getApi().onerror = this.events.error;
        },
        _listener: function() {
            var _self = this;

            this.env = $.midgardmvc.helpers._cometclass._env.generate(this);

            this.env.setUrl = function(val) {
    			if (this.getType() > 1) {
    				val = '{0}{1}cometType={2}&cometName={3}'.format(val, val.search("\\?") > -1 ? '&' : '?', this.getType(), this.getName());

    				if (this.getType() == 2) {
    				    this.getTunnel().setAttribute('src', val);
    				}
    			} else {
    			    this.getTunnel().env.setUrl(val);
    			}

    			this.url = val;
    		};
    		
            this.events = {
        		change: function(self) {
        			var response = null;
        			if (self.env.getType() == 2) {
        			    response = arguments[0].data
        			} else {
        				response = self.env.getApi().responseText.split("<end />");
        				response = response[response.length-1];
        			}
           			self._cometApi_.events.push(response);
        		},
        		push: function(resp){}
            };

            this.actions = {
        		abort: function() {
        			switch (this.env.getType()) {
        				case 1:
        					this.env.getTunnel().abort();
        				break;
        				case 2:
        					document.body.removeChild(this.env.getTunnel());
        				break;
        				case 3:
        					this.env.getTunnel().body.innerHTML = '<iframe src="about:blank"></iframe>';
        				break;
        			}
        		},
        		send: function() {
        			switch (this.env.getType()) {
        				case 1:
        					this.env.getTunnel().send();
        				break;
        				case 2:
        					document.body.appendChild(this.env.getTunnel());
        					this.env.getTunnel().addEventListener( this.env.getName(), this.events.change, false );
        				break;
        				case 3:
        					this.env.getTunnel().open();
        					this.env.getTunnel().write('<html><body></body></html>');
        					this.env.getTunnel().close();
        					this.env.getTunnel().parentWindow._cometObject = this;
        					this.env.getTunnel().body.innerHTML = '<iframe src="{0}"></iframe>'.format(this.env.getUrl());
        			}
        		}
            };
            
    		this.env.setName("midcomComet");
    		this.env.setType($.browser.ie ? 3 : $.browser.opera ? 2 : 1);
    		this.env.setTunnel(
    			this.env.getType() == 3 ? new ActiveXObject("htmlfile"):
    			this.env.getType() == 2 ? document.createElement("event-source"):
    			new $.midgardmvc.helpers.cometclass._xhr()
    		);
        }
    };
    $.midgardmvc.helpers.cometclass = {
    };
    $.extend($.midgardmvc.helpers.cometclass, {
        listen: function() {
            return new $.midgardmvc.helpers.cometclass._listener();
        },
        send: function() {
            return new $.midgardmvc.helpers.cometclass._xhr();
        },
        _listener: function() {
            var inst = new $.midgardmvc.helpers._cometclass._listener();
            inst.abort = inst.actions.abort;
            inst.send = inst.actions.send;
            
            return inst;
        },
        _xhr: function() {
            var inst = new $.midgardmvc.helpers._cometclass._xhr();
            inst.abort = inst.actions.abort;
            inst.send = inst.actions.send;
            
            return inst;
        }
    });
    
})(jQuery);