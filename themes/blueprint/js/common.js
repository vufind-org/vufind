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

function toggleMenu(elemId) {
    var elem = $("#"+elemId);
    if (elem.hasClass("offscreen")) {
        elem.removeClass("offscreen");
    } else {
        elem.addClass("offscreen");
    }
}

function moreFacets(name) {
    $("#more"+name).addClass("offscreen");
    $("#narrowGroupHidden_"+name).removeClass("offscreen");
}

function lessFacets(name) {
    $("#more"+name).removeClass("offscreen");
    $("#narrowGroupHidden_"+name).addClass("offscreen");
}

function filterAll(element, formId) {
    //  Look for filters (specifically checkbox filters)
    if (formId == null) {
        formId = "searchForm";
    }
    $("#" + formId + " :input[type='checkbox'][name='filter[]']")
        .attr('checked', element.checked);
    $("#" + formId + " :input[type='checkbox'][name='dfApplied']")
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

// Advanced facets
function updateOrFacets(url, op) {
  window.location.assign(url);
  var list = $(op).parents('dl');
  var header = $(list).find('dt');
  list.html(header[0].outerHTML+'<div class="info">'+vufindString.loading+'...</div>');
}
function setupOrFacets() {
  var facets = $('.facetOR');
  for(var i=0;i<facets.length;i++) {
    var $facet = $(facets[i]);
    if($facet.hasClass('applied')) {
      $facet.prepend('<input type="checkbox" checked onChange="updateOrFacets($(this).parent().attr(\'href\'), this)"/>');
    } else {
      $facet.before('<input type="checkbox" onChange="updateOrFacets($(this).next(\'a\').attr(\'href\'), this)"/>');
    }
  }
}

// Phone number validation
var libphoneTranslateCodes = ["libphonenumber_invalid", "libphonenumber_invalidcountry", "libphonenumber_invalidregion", "libphonenumber_notanumber", "libphonenumber_toolong", "libphonenumber_tooshort", "libphonenumber_tooshortidd"]
var libphoneErrorStrings = ["Phone number invalid", "Invalid country calling code", "Invalid region code", "The string supplied did not seem to be a phone number", "The string supplied is too long to be a phone number", "The string supplied is too short to be a phone number", "Phone number too short after IDD"];
function phoneNumberFormHandler(numID, regionCode) {
  var phoneInput = document.getElementById(numID);
  var number = phoneInput.value;
  var valid = isPhoneNumberValid(number, regionCode);
  if(valid !== true) {
    if(typeof valid === 'string') {
      for(var i=libphoneErrorStrings.length;i--;) {
        if(valid.match(libphoneErrorStrings[i])) {
          valid = vufindString[libphoneTranslateCodes[i]];
        }
      }
    } else {
      valid = vufindString['libphonenumber_invalid'];
    }
    $(phoneInput).siblings('.phone-error').html(valid);
  } else {
    $(phoneInput).siblings('.phone-error').html('');
  }
  return valid == true;
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

    // attach click event to the visualization help links
    $('a.visualizationHelp').click(function(){
        window.open(path + '/Help/Home?topic=visualization', 'Help', 'width=625, height=510');
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

        var holder = $(this).next('.qrcodeHolder');

        if (holder.find('img').length == 0) {
            // We need to insert the QRCode image
            var template = holder.find('.qrCodeImgTag').html();
            holder.html(template);
        }

        holder.toggle();

        return false;
    });

    // Print
    var url = window.location.href;
    if(url.indexOf('?' + 'print' + '=') != -1  || url.indexOf('&' + 'print' + '=') != -1) {
        $("link[media='print']").attr("media", "all");
        window.print();
    }

    // Collapsing facets
    $('.narrowList dt').click(function(){
      $(this).parent().toggleClass('open');
      $(this.className.replace('facet_', '#narrowGroupHidden_')).toggleClass('open');
    });

    // Support holds cancel list buttons:
    function cancelHolds(type) {
      var typeIDS = type+'IDS';
      var selector = '[name="'+typeIDS+'[]"]';
      if (type == 'cancelSelected') {
          selector += ':checked';
      }
      var ids = $(selector);
      var cancelIDS = [];
      for(var i=0;i<ids.length;i++) {
        cancelIDS.push(ids[i].value);
      }
      // Skip submission if no selection.
      if (cancelIDS.length < 1) {
          return false;
      }
      var postParams = {'confirm':0};
      postParams[type] = 1;
      postParams[typeIDS] = cancelIDS;
      getLightbox('MyResearch', 'Holds', '', '', '', 'MyResearch', 'Holds', '', postParams);
      return false;
    }
    $('.holdCancel').unbind('click').click(function(){
      return cancelHolds('cancelSelected');
    });
    $('.holdCancelAll').unbind('click').click(function(){
      return cancelHolds('cancelAll');
    });

    // Bulk action ribbon
    function bulkActionRibbonLightbox(action) {
      var ids = [];
      var checks = $('.recordNumber [type=checkbox]:checked');
      $('.bulkActionButtons .error').remove();
      if(checks.length == 0) {
        $('.bulkActionButtons').prepend('<div class="error">'+vufindString.bulk_noitems_advice+'</div>');
        return false;
      }
      for(var i=0;i<checks.length;i++) {
        ids.push(checks[i].value);
      }
      getLightbox('Cart', action, ids, null, null, 'Cart', action, '', {ids:ids});
      return false;
    }
    $('#ribbon-email').unbind('click').click(function(){
      return bulkActionRibbonLightbox('Email');
    });
    $('#ribbon-export').unbind('click').click(function(){
      return bulkActionRibbonLightbox('Export');
    });
    $('#ribbon-save').unbind('click').click(function(){
      return bulkActionRibbonLightbox('Save');
    });
    $('#ribbon-print').unbind('click').click(function(){
      //redirect page
      var url = path+'/Records/Home?print=true';
      var checks = $('.recordNumber [type=checkbox]:checked');
      $('.bulkActionButtons .error').remove();
      if(checks.length == 0) {
        $('.bulkActionButtons').prepend('<div class="error">'+vufindString.bulk_noitems_advice+'</div>');
        return false;
      }
      for(var i=0;i<checks.length;i++) {
        url += '&id[]='+checks[i].value;
      }
      document.location.href = url;
    });

    //ContextHelp
    contextHelp.init();
    contextHelp.contextHelpSys.load();

    // Advanced facets
    setupOrFacets();
});
