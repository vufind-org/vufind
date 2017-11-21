// jQuery.editable.js v1.1.1
// http://shokai.github.io/jQuery.editable
// (c) 2012-2013 Sho Hashimoto <hashimoto@shokai.org>
// (c) 2015 The National Library of Finland
// The MIT License
(function($){
    var escape_html = function(str){
        return str.replace(/</gm, '&lt;').replace(/>/gm, '&gt;');
    };
    var unescape_html = function(str){
        return str.replace(/&lt;/gm, '<').replace(/&gt;/gm, '>');
    };

    $.fn.editable = function(event, callbacks, settings){
        $(['start','finish']).each(function(ind, ev) {
            if(typeof(callbacks[ev]) === 'undefined') {
                callbacks[ev] = function(){};
            }
        });

        if(typeof event === 'string'){
            var triggers = [this];
            var action = event;
            var type = 'input';
        }
        else if(typeof event === 'object'){
            var triggers = event.triggers || [this];
            var action = event.action || 'click';
            var type = event.type || 'input';
        }
        else{
            throw('Argument Error - jQuery.editable("click", function(){ ~~ })');
        }

        var target = this;
        var edit = {};

        edit.start = function(e){
            $.each(triggers, function(ind, obj) {
                obj.unbind(action === 'clickhold' ? 'mousedown' : action);
                if (obj !== target) {
                    obj.hide();
                }
            });

            var old_value = (
                type === 'textarea' ?
                    target.html().replace(/<br class="newline"( \/)?>/gm, '\n').replace(/&gt;/gm, '>').replace(/&lt;/gm, '<') :
                    target.text()
            ).replace(/^\s+/,'').replace(/\s+$/,'');

            var input = type === 'textarea' ? $('<textarea>') : $('<input>');
            var w = target.width();
            if (typeof(settings['minWidth']) !== 'undefined') {
                w = w < settings['minWidth'] ? settings['minWidth'] : w;
            }

            input.val(old_value).
                css('width', w ).
                css('font-size','100%').
                css('margin',0).attr('id','editable_'+(new Date()*1)).
                addClass('editable');
            if(type === 'textarea') {
                var h = target.height();
                if (typeof(settings['addToHeight'])) {
                    h += settings['addToHeight'];
                }
                input.css('height', h);
            }

            var finish = function(cancel){
                var result = cancel ? old_value : input.val().replace(/^\s+/,'').replace(/\s+$/,'');
                var html = escape_html(result);
                if(type === 'textarea') html = html.replace(/[\r\n]/gm, '<br class="newline"/>');
                target.html(html);
                callbacks['finish']({value : result, target : target, old_value : old_value, cancel: cancel});
                edit.register();
                $.each(triggers, function(ind, obj) {
                    if (obj !== target) {
                        obj.show();
                    }
                });
            };

            input.keydown(function(e){
                if (e.keyCode === 27) {
                    // Enter
                    finish(true);
                } else if (type === 'input' && e.keyCode === 13) {
                    // Esc
                    finish(false);
                }
            });

            target.html(input);

            input.focus();
            input.blur(function() { finish(false); });
            callbacks['start']({value : 'a', target : target});
        };

        edit.register = function(){
            if(action === 'clickhold'){
                var tid = null;
                $.each(triggers, function(ind, obj) {
                    obj.unbind('mousedown').bind('mousedown', function(e){
                        tid = setTimeout(function(){
                            edit.start(e);
                        }, 500);
                    });
                    obj.unbind('mouseup mouseout').bind('mouseup mouseout', function(e){
                        clearTimeout(tid);
                    });
                });
            }
            else{
                $.each(triggers, function(ind, obj) {
                    obj.unbind(action).bind(action, edit.start);
                });
            }
        };
        edit.register();

        return this;
    };
})(jQuery);
