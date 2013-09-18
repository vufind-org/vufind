/*global getLightbox, path, vufindString*/

/**
 * Initialize common functions and event handlers.
 */
// disable caching for all AJAX requests
$.ajaxSetup({cache: false});

// set global options for the jQuery validation plugin
$.validator.setDefaults({
    errorClass: 'invalid'
});

// add a modified version of the original phoneUS rule
// to accept only 10-digit phone numbers
$.validator.addMethod("phoneUS", function(phone_number, element) {
    phone_number = phone_number.replace(/[\-\s().]+/g, "");
    return this.optional(element) || phone_number.length > 9 &&
        phone_number.match(/^(\([2-9]\d{2}\)|[2-9]\d{2})[2-9]\d{2}\d{4}$/);
}, 'Please specify a valid phone number');

function toggleMenu(elemId) {
    var elem = $("#"+elemId);
    if (elem.hasClass("offscreen")) {
        elem.removeClass("offscreen");
    } else {
        elem.addClass("offscreen");
    }
}

function moreFacets(name) {
    $("#more"+name).hide();
    $("#narrowGroupHidden_"+name).removeClass("offscreen");
}

function lessFacets(name) {
    $("#more"+name).show();
    $("#narrowGroupHidden_"+name).addClass("offscreen");
}

function filterAll(element, formId) {
    //  Look for filters (specifically checkbox filters)
    if (formId == null) {
        formId = "searchForm";
    }
    $("#" + formId + " :input[type='checkbox'][name='filter[]']")
        .attr('checked', element.checked);
}

function extractParams(str) {
    var params = {};
    var classes = str.split(/\s+/);
    for(var i = 0; i < classes.length; i++) {
        if (classes[i].indexOf(':') > 0) {
            var pair = classes[i].split(':');
            params[pair[0]] = pair[1];
        }
    }
    return params;
}

function initAutocomplete() {
    $('input.autocomplete').each(function() {
        var lastXhr = null;
        var params = extractParams($(this).attr('class'));
        var maxItems = params.maxItems > 0 ? params.maxItems : 10;
        var $autocomplete = $(this).autocomplete({
            source: function(request, response) {
                var type = params.type;
                if (!type && params.typeSelector) {
                    type = $('#' + params.typeSelector).val();
                }
                var searcher = params.searcher;
                if (!searcher) {
                    searcher = 'Solr';
                }
                // Abort previous access if one is defined
                if (lastXhr !== null && typeof lastXhr["abort"] != "undefined") {
                    lastXhr.abort();
                }
                lastXhr = $.ajax({
                    url: path + '/AJAX/JSON',
                    data: {method:'getACSuggestions',type:type,q:request.term,searcher:searcher},
                    dataType:'json',
                    success: function(json) {
                        if (json.status == 'OK' && json.data.length > 0) {
                            response(json.data.slice(0, maxItems));
                        } else {
                            $autocomplete.autocomplete('close');
                        }
                    }
                });
            }
        });
    });
}

function htmlEncode(value){
    if (value) {
        return jQuery('<div />').text(value).html();
    } else {
        return '';
    }
}

// mostly lifted from http://docs.jquery.com/Frequently_Asked_Questions#How_do_I_select_an_element_by_an_ID_that_has_characters_used_in_CSS_notation.3F
function jqEscape(myid) {
    return String(myid).replace(/(:|\.)/g,'\\$1');
}

function printIDs(ids)
{
    if(ids.length == 0) {
        return false;
    }
    var parts = [];
        $(ids).each(function() {
       parts[parts.length] = encodeURIComponent('id[]') + '=' + encodeURIComponent(this);
        });
    var url =  path + '/Records?print=true&' + parts.join('&');
    window.open(url);
    return true;
}

var contextHelp = {
    init: function() {
        $('body').append('<table cellspacing="0" cellpadding="0" id="contextHelp"><tbody><tr class="top"><td class="left"></td><td class="center"><div class="arrow up"></div></td><td class="right"></td></tr><tr class="middle"><td></td><td class="body"><div id="closeContextHelp"></div><div id="contextHelpContent"></div></td><td></td></tr><tr class="bottom"><td class="left"></td><td class="center"><div class="arrow down"></div></td><td class="right"></td></tr></tbody></table>');
    },

    hover: function(listenTo, widthOffset, heightOffset, direction, align, msgText) {
        $(listenTo).mouseenter(function() {
            contextHelp.contextHelpSys.setPosition(listenTo, widthOffset, heightOffset, direction, align, '', false);
            contextHelp.contextHelpSys.updateContents(msgText);
        });
        $(listenTo).mouseleave(function() {
            contextHelp.contextHelpSys.hideMessage();
        });
    },

    flash: function(id, widthOffset, heightOffset, direction, align, msgText, duration) {
        this.contextHelpSys.setPosition(id, widthOffset, heightOffset, direction, align);
        this.contextHelpSys.updateContents(msgText);
        setTimeout(this.contextHelpSys.hideMessage, duration);
    },

    contextHelpSys: {
        CHTable:"#contextHelp",
        CHContent:"#contextHelpContent",
        arrowUp:"#contextHelp .arrow.up",
        arrowDown:"#contextHelp .arrow.down",
        closeButton:"#closeContextHelp",
        showCloseButton: true,
        curElement:null,
        curOffsetX:0,
        curOffsetY:0,
        curDirection:"auto",
        curAlign:"auto",
        curMaxWidth:null,
        isUp:false,
        load:function(){
            $(contextHelp.contextHelpSys.closeButton).click(contextHelp.contextHelpSys.hideMessage);
            $(window).resize(contextHelp.contextHelpSys.position);},
        setPosition:function(element, offsetX, offsetY, direction, align, maxWidth, showCloseButton){
            if(element==null){element=document;}
            if(offsetX==null){offsetX=0;}
            if(offsetY==null){offsetY=0;}
            if(direction==null){direction="auto";}
            if(align==null){align="auto";}
            if(showCloseButton==null){showCloseButton=true;}
            contextHelp.contextHelpSys.curElement=$(element);
            contextHelp.contextHelpSys.curOffsetX=offsetX;
            contextHelp.contextHelpSys.curOffsetY=offsetY;
            contextHelp.contextHelpSys.curDirection=direction;
            contextHelp.contextHelpSys.curAlign=align;
            contextHelp.contextHelpSys.curMaxWidth=maxWidth;
            contextHelp.contextHelpSys.showCloseButton=showCloseButton;},
        position:function(){
            if(!contextHelp.contextHelpSys.isUp||!contextHelp.contextHelpSys.curElement.length){return;}
            var offset=contextHelp.contextHelpSys.curElement.offset();
            var left=parseInt(offset.left, 10)+parseInt(contextHelp.contextHelpSys.curOffsetX, 10);
            var top=parseInt(offset.top, 10)+parseInt(contextHelp.contextHelpSys.curOffsetY, 10);
            var direction=contextHelp.contextHelpSys.curDirection;
            var align=contextHelp.contextHelpSys.curAlign;
            if(contextHelp.contextHelpSys.curMaxWidth){
                $(contextHelp.contextHelpSys.CHTable).css("width",contextHelp.contextHelpSys.curMaxWidth);
            } else {
                $(contextHelp.contextHelpSys.CHTable).css("width","auto");
            }
            if (direction=="auto") {
                if (parseInt(top, 10)-parseInt($(contextHelp.contextHelpSys.CHTable).height()<$(document).scrollTop(), 10)) {
                    direction="down";
                } else {
                    direction="up";
                }
            }
            if(direction=="up"){
                top = parseInt(top, 10) - parseInt($(contextHelp.contextHelpSys.CHTable).height(), 10);
                $(contextHelp.contextHelpSys.arrowUp).css("display","none");
                $(contextHelp.contextHelpSys.arrowDown).css("display","block");
            } else {
                if(direction=="down"){
                    top = parseInt(top, 10) + parseInt(contextHelp.contextHelpSys.curElement.height(), 10);
                    $(contextHelp.contextHelpSys.arrowUp).css("display","block");
                    $(contextHelp.contextHelpSys.arrowDown).css("display","none");
                }
            }
            if(align=="auto"){
                if(left+parseInt($(contextHelp.contextHelpSys.CHTable).width()>$(document).width(), 10)){
                    align="left";
                } else {
                    align="right";
                }
            }
            if(align=="right"){
                left-=24;
                $(contextHelp.contextHelpSys.arrowUp).css("background-position","0 0");
                $(contextHelp.contextHelpSys.arrowDown).css("background-position","0 -6px");
            }
            else{
                if(align=="left"){
                    left-=parseInt($(contextHelp.contextHelpSys.CHTable).width(), 10);
                    left+=24;
                    $(contextHelp.contextHelpSys.arrowUp).css("background-position","100% 0");
                    $(contextHelp.contextHelpSys.arrowDown).css("background-position","100% -6px");
                }
            }
            if(contextHelp.contextHelpSys.showCloseButton) {
                $(contextHelp.contextHelpSys.closeButton).show();
            } else {
                $(contextHelp.contextHelpSys.closeButton).hide();
            }
            $(contextHelp.contextHelpSys.CHTable).css("left",left + "px");
            $(contextHelp.contextHelpSys.CHTable).css("top",top + "px");
        },
        updateContents:function(msg){
            contextHelp.contextHelpSys.isUp=true;
            $(contextHelp.contextHelpSys.CHContent).empty();
            $(contextHelp.contextHelpSys.CHContent).append(msg);
            contextHelp.contextHelpSys.position();
            $(contextHelp.contextHelpSys.CHTable).hide();
            $(contextHelp.contextHelpSys.CHTable).fadeIn();
        },
        hideMessage:function(){
            if(contextHelp.contextHelpSys.isUp){
                $(contextHelp.contextHelpSys.CHTable).fadeOut();
                contextHelp.contextHelpSys.isUp=false;
            }
        }
    }
};

function extractDataByClassPrefix(element, prefix)
{
    var classes = $(element).attr('class').split(/\s+/);

    for (var i = 0; i < classes.length; i++) {
        if (classes[i].substr(0, prefix.length) == prefix) {
            return classes[i].substr(prefix.length);
        }
    }

    // No matching controller class was found!
    return '';
}

// extract a controller name from the classes of the provided element
function extractController(element)
{
    return extractDataByClassPrefix(element, 'controller');
}

// extract a record source name from the classes of the provided element; default
// to 'VuFind' if no source found
function extractSource(element)
{
    var x = extractDataByClassPrefix(element, 'source');
    return x.length == 0 ? 'VuFind' : x;
}

$(document).ready(function(){
    // initialize autocomplete
    initAutocomplete();

    // put focus on the "mainFocus" element
    $('.mainFocus').each(function(){ $(this).focus(); } );

    // support "jump menu" dropdown boxes
    $('select.jumpMenu').change(function(){ $(this).parent('form').submit(); });

    // attach click event to the "keep filters" checkbox
    $('#searchFormKeepFilters').change(function() { filterAll(this); });

    // attach click event to the search help links
    $('a.searchHelp').click(function(){
        window.open(path + '/Help/Home?topic=search', 'Help', 'width=625, height=510');
        return false;
    });

    // attach click event to the advanced search help links
    $('a.advsearchHelp').click(function(){
        window.open(path + '/Help/Home?topic=advsearch', 'Help', 'width=625, height=510');
        return false;
    });

    // assign click event to "email search" links
    $('a.mailSearch').click(function() {
        var id = this.id.substr('mailSearch'.length);
        var $dialog = getLightbox('Search', 'Email', id, null, this.title, 'Search', 'Email', id);
        return false;
    });

    // assign action to the "select all checkboxes" class
    $('input[type="checkbox"].selectAllCheckboxes').change(function(){
        $(this.form).find('input[type="checkbox"]').attr('checked', $(this).is(':checked'));
    });

    // attach mouseover event to grid view records
    $('.gridCellHover').mouseover(function() {
        $(this).addClass('gridMouseOver');
    });

    // attach mouseout event to grid view records
    $('.gridCellHover').mouseout(function() {
        $(this).removeClass('gridMouseOver');
    });

    // assign click event to "viewCart" links
    $('a.viewCart').click(function() {
        var $dialog = getLightbox('Cart', 'Home', null, null, this.title, '', '', '', {viewCart:"1"});
        return false;
    });
    
    // handle QR code links
    $('a.qrcodeLink').click(function() {
        if ($(this).hasClass("active")) {
            $(this).html(vufindString.qrcode_show).removeClass("active");
        } else {
            $(this).html(vufindString.qrcode_hide).addClass("active");
        }
        $(this).next('.qrcodeHolder').toggle();
        return false;
    });

    // Print
    var url = window.location.href;
    if(url.indexOf('?' + 'print' + '=') != -1  || url.indexOf('&' + 'print' + '=') != -1) {
        $("link[media='print']").attr("media", "all");
        window.print();
    }

    //ContextHelp
    contextHelp.init();
    contextHelp.contextHelpSys.load();
});