function triggerVirtualKeyboard(accept, enter) {
    $('.virtual-keyboard-greek').off().click(function() {
       showKb('el', false, accept, enter);
    });
    $('.virtual-keyboard-hebrew').off().click(function() {
       showKb('he', true, accept, enter);
    });
}


function showKb(layout, right_to_left, accept, enter) {
    var accept_label =  accept + ':' + accept;
    var enter_label =  enter + ':' + enter;
    var kb = $('.searchForm_lookfor:visible').keyboard(
              { position :  { of : null,
                              my : 'center top',
                              at : 'center top',
                              // used when "usePreview" is false
                              // (centers keyboard at bottom of the input/textarea)
                              at2: 'center bottom'
                            },
                display:    { 'accept' : accept_label,
                              'enter'  : enter_label},
                autoAccept: false,
                usePreview: false,
              }).getkeyboard();
    kb.options.layout = layout;
    kb.options.rtl = right_to_left;
    kb.redraw();
    // Make sure we do open the keyboard again on focus the standard search field
    $.extend($.keyboard.keyaction, {
        accept : function(base) {
            base.close(true);
            kb.destroy();
            return false;
        },
        enter : function(base) {
            if (base.el.nodeName === "INPUT") {
                base.close(true);
                kb.destroy();
                return false;
            }
        }
    });
}
