/**
 * @package midcom_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

(function($){

    $.midcom.services.uimessages = {
        config: {
            enable_comet: false
        }        
    };
    $.midcom.services.uimessages.midgard = function(element, config) {
        this.holder = $(element);
        
        this.config = $.midcom.services.configuration.merge(
            $.midcom.services.uimessages.config,
            config
        );
        
        this.config.holder_class = this.holder.attr('class');
        
        $.midcom.logger.log('midcom.services.uimessages.midgard inited');
        $.midcom.logger.debug(this.config);
        
        if (this.config.enable_comet) {
            this.start_comet();
        }
        
        $.midcom.events.signals.trigger('midcom.services.uimessages::midgard-inited');
    };
    $.extend($.midcom.services.uimessages.midgard.prototype, {
        start_comet: function() {            
            var _self = this;
            var response_method = function(resp) {
                resp = $.midcom.helpers.json.parse(resp);
                
                if (typeof resp.length != 'undefined') {
                    for (var i=0; i<resp.length; i++) {
                        _self.create_message(resp[i]);
                    }
                }
            };

            var req = jQuery.midcom.helpers.comet.start('/mgd:comet/messages/', response_method);
        },
        create_message: function(data) {
            console.log("create_message");
            console.log(data);
        }
    });

    jQuery.fn.extend({
    	midcom_services_uimessages_midgard: function(config) {
    	    return new jQuery.midcom.services.uimessages.midgard(jQuery(this), config);
    	}
    });

})(jQuery);