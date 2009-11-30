/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

(function($){

    $.midgardmvc.services.uimessages = {
        config: {
            enable_comet: false
        }        
    };
    $.midgardmvc.services.uimessages.midgard = function(element, config) {
        this.holder = $(element);
        
        this.config = $.midgardmvc.services.configuration.merge(
            $.midgardmvc.services.uimessages.config,
            config
        );
        
        this.config.holder_class = this.holder.attr('class');
        
        $.midgardmvc.logger.log('midcom.services.uimessages.midgard inited');
        $.midgardmvc.logger.debug(this.config);
        
        if (this.config.enable_comet) {
            this.start_comet();
        }
        
        $.midgardmvc.events.signals.trigger('midcom.services.uimessages::midgard-inited');
    };
    $.extend($.midgardmvc.services.uimessages.midgard.prototype, {
        start_comet: function() {            
            var _self = this;
            var response_method = function(resp) {
                resp = $.midgardmvc.helpers.json.parse(resp);
                
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
    	midgardmvc_services_uimessages_midgard: function(config) {
    	    return new jQuery.midcom.services.uimessages.midgard(jQuery(this), config);
    	}
    });

})(jQuery);