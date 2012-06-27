/*
 * JS Frame for Zooming and Rotating an Image
 *
 * - Only has one method: $(container).inspector(img-src);
 * - Used for initializing             ^^^^^^^^^
 *      and loading images
 * 
 * Chris Hallberg <crhallberg@gmail.com
 * Version 2.0b3 : 30 May 2012     // Map offset bugs
 * Version 2.0b2 : 25 May 2012     // Fixed image loading bugs
 * Version 2.0b1 : 19 October 2011 // FF bounding problems
 * Version 2.0   : 18 October 2011 // Major rewrite of bounding code :)
 */
 
/* Full Changelog on Bottom */

/**
 * KNOWN BUGS
 *  - Inital pane location off-center
 *  - Scrolling while zooming causes the drag handle to misplace
 *  - IE: refreshing does not re-render image. At this point, blaming IE because it doesn't make sense
 *  - IE: zooming all the way in while the image is rotated, sets the image back to unrotated, but not the drag-master
 */
 
(function($){
  var methods = {
    init : function(elem) {
      $(elem).html('')
        .addClass('jquery_inspector')
        .css({
          'overflow':'hidden',
          'position':'relative',
          'width'   :$(elem).attr('width'),
          'height'  :$(elem).attr('height'),
          'background':'#000'
        })
      $('<div>').addClass("drag-master")
        .css({'margin':'0'})
        .appendTo(elem)
        .attr('unselectable', 'on') // prevent selection
        .each(function(){this.onselectstart=function(){return false}});
      $('<span>').addClass("loading")
        .html('Loading...')
        .appendTo(elem);
      $('<img/>').addClass('doc').appendTo('.drag-master',elem);
      // ZOOM DISPLAY
      $('<div>').addClass('zoom_level')
        .html('<a class="minus">&ndash;</a> <span></span>  <a class="plus">+</a>')
        .appendTo(elem);
      // ZOOM SET TEXT BOX
      $('<style></style>').appendTo('head');
      $('<div></div>').addClass('zoom-set')
        .html('<input type="number" size="3" min="5" value="100" max="800" step="5" id="new-zoom-level">')
        .appendTo(elem).hide();
      $('<button>%</button>').appendTo('.zoom-set',elem);
      // ROTATE LEFT ARROW
      $('<div>').addClass('turn_left').html('&nbsp;').appendTo(elem);
      // ROTATE RIGHT ARROW
      $('<div>').addClass('turn_right').html('&nbsp;').appendTo(elem);
      // MAP
      $('<div>').addClass('doc_map')
        .html('<img src=""><div class="pane">&nbsp;</div>')
        .appendTo(elem);
    },
    
    // ASSIGN CLICKS DEPENDENT ON STATE
    setHandlers: function(elem,state) {
      $(elem).resize(function() {
          methods.setDrag($('.doc',elem),elem,state);
        });
      $(window)
        .unbind('load resize scroll')
				.load(function() {
          methods.setDrag($('.doc',elem),$(elem),state);
        })
        .resize(function() {
          methods.setDrag($('.doc',elem),elem,state);
        })
        .scroll(function() {
          methods.setDrag($('.doc',elem),elem,state);
        });
      $('.drag-master',elem)  
        .unbind('click mousewheel') 
        .click(function(){$('.zoom-set',elem).hide()})  // hide bubble on unfocus
        // ZOOMING IN AND OUT   
        .mousewheel(function(event,delta){              // zoom with mouse scroll
          event.preventDefault();
          methods.zoom.call(this,elem,event,delta,state);
        });
      $(elem).find('.doc_map .pane')
        .unbind('mousewheel') 
        .mousewheel(function(event,delta){              // zoom with mouse scroll on map
          event.preventDefault();
          var newZoom = state.zoom * (1+(delta/4));
          methods.setZoom(elem,newZoom,state);
        });
      $('.turn_left',elem)
        .unbind('click') 
				.click(function() {           // rotate image counter-clockwise
          state.angle -= 90;
          $(elem).find('.doc,.doc_map img').rotate(state.angle);
          // center
          methods.resize($('.doc',elem),elem,state);
          methods.setDrag($('.doc',elem),elem,state);
        })
      $('.turn_right',elem)
        .unbind('click') 
				.click(function() {          // rotate image clockwise
          state.angle += 90;
          $(elem).find('.doc,.doc_map img').rotate(state.angle);
          // center
          methods.resize($('.doc',elem),elem,state);
          methods.setDrag($('.doc',elem),elem,state);
        });     
      // IE doc_map rotation
      if($.browser.msie) {
        $(elem).find('.turn_left,.turn_right').click(function() {   
          if(state.angle%180 > 0) {
            var margin = (state.mapHeight-state.mapWidth)/2;
            $(elem).find('.doc_map img').css({
              'left':state.mapX-margin,
              'top' :state.mapY+margin
            });
          } else {
            $(elem).find('.doc_map img').css({
              'left':state.mapX,
              'top' :state.mapY
            });
          }
        });
      }
      $('.zoom-set button',elem).click(function () {    // zoom to typed level
          methods.setZoom(elem,$('#new-zoom-level').val()/100,state);
        });
      $('.zoom_level span',elem).click(function() {          // clicking the percent opens zoom bubble
          $('.zoom-set',elem).toggle();
        });
      $('.zoom_level .plus',elem).click(function() {         // zoom in button
          methods.setZoom(elem,state.zoom*1.5,state);
        })
      $('.zoom_level .minus',elem).click(function() {        // zoom out button
          methods.setZoom(elem,state.zoom*.5,state);
        })
    },
    
    load: function(elem,src) {
      //alert('load');
      $('.loading',elem).show();  
      $('.drag-master').css({'cursor':'progress'});
      var state = {
        angle:0,
        padding:0,
        img:src
      };
      $('.doc',elem)
        .css({
          'width':'auto',
          'height':'auto'
        })
        .unbind('load')
        .load(function() {
          //alert('img loaded');
          state.width  = $(this).width();
          state.height = $(this).height();
          state.size = Math.max(state.width,state.height);
          state.zoom = $(elem).width()/state.width;
          $(this).css({
            'position':'absolute'
          })
          state = methods.fit(elem,this,state);
          $('.zoom_level span',elem).html(Math.round(state.zoom*100)+"%");
          // stop loading
          $('.loading',elem).hide();
          $(this).show();
          // map
          state.mapSize = 140/state.size;
          state.mapWidth = (state.width*state.mapSize);
          state.mapHeight = (state.height*state.mapSize);
          state.mapX = ((150-state.mapWidth)/2);
          state.mapY = ((150-state.mapHeight)/2);
          $(elem).find('.doc_map img').css({
            'width' :state.mapWidth,
            'height':state.mapHeight,
            'left':state.mapX,
            'top':state.mapY
          }).rotate(0);
          // trying to improve drag performance
          $(elem).find('.doc,.doc_map img').rotate(360);
          methods.resize($('.doc',elem),elem,state);
          methods.setDrag($('.doc',elem),elem,state);
          methods.mapDrag({position:{left:$(elem).offset().left,top:$(elem).offset().top}},elem,this,state);
          // show signs of life
          $('.drag-master').css({'cursor':'move'});
        })
        .attr('src',src)
        .hide();
      $(elem).find('.doc_map img').attr('src',src);
      return state;
    },
    
    zoom : function(elem,event,delta,state) {
      var newZoom = state.zoom * (1+(delta/4));
      if(newZoom < state.minZoom) newZoom = state.minZoom;
      else if(newZoom > 8) newZoom = 8;
      if(newZoom == state.zoom) return;
      state.center = [
        event.pageX,
        event.pageY,
        (event.pageX-$('.drag-master',elem).offset().left)/state.zoom,
        (event.pageY-$('.drag-master',elem).offset().top) /state.zoom
      ];
      state.zoom = newZoom;
      methods.resize($('.doc',elem),elem,state,true);
    },
    
    setZoom : function(elem,newZoom,state) {
      if(newZoom < state.minZoom) newZoom = state.minZoom;
      if(newZoom > 8) newZoom = 8; // 800%
      if(state.zoom == newZoom) return;
      var centerX = $(elem).offset().left+($(elem).width()/2);
      var centerY = $(elem).offset().top+($(elem).height()/2);
      state.center = [
        centerX,
        centerY,
        (centerX-$(elem).find('.drag-master').offset().left)/state.zoom,
        (centerY-$(elem).find('.drag-master').offset().top) /state.zoom
      ];
      state.zoom = newZoom;
      methods.resize($(elem).find('.doc'),elem,state,true);
      $('.zoom-set',elem).hide();
    },
    
    setDrag : function(pic,elem,state) {
      // container sides
      var eOffset = $(elem).offset();
      var left = eOffset.left;
      var right = eOffset.left+$(elem).width();
      var top = eOffset.top;
      var bottom = eOffset.top+$(elem).height();
      // image size
      var width  = $(pic).width();
      var height = $(pic).height();
      // rotation fix
      if(!$.browser.msie && state.angle%180 > 0) {
        width = height;
        height = $(pic).width();
      }
      
      var DM = $(elem).find('.drag-master');
      var offsetX = (DM.width()-width)/2;
      var offsetY = (DM.height()-height)/2;
      var vals = [
        left   - offsetX,
        top    - offsetY,
        right  - offsetX - width,
        bottom - offsetY - height
      ];
      var cont = [];
      for(i in vals) cont[i] = vals[i];
      
      if(width > $(elem).width()) {
        cont[0] = vals[2];
        cont[2] = vals[0];
      }
      if(height > $(elem).height()) {
        cont[1] = vals[3];
        cont[3] = vals[1];
      }
      
      $(elem).find('.drag-master').draggable({
          containment:cont,
          scroll:false,
          drag:function(event,ui) {
            methods.mapDrag(ui,elem,pic,state);
          }
        });
    },
    
    // FIT PICTURE TO FRAME
    fit : function(elem,img,state) {
      if($(elem).width() > $(elem).height()) {
        state.zoom = ($(elem).height()*.95)/state.size;
      } else {
        state.zoom = ($(elem).width()*.95)/state.size;
      }
      state.minZoom = state.zoom*.9;
      state.center = [
        $(elem).offset().left+$(elem).width()/2,
        $(elem).offset().top+$(elem).height()/2,
        0,
        0
      ];
      methods.resize(img,elem,state,true);
      // center
      $('.drag-master',elem).css({
        //'background':'#222', //debug
        'top'   :Math.floor(($(elem).height()-(state.size*state.zoom))/2)-1,
        'left'  :Math.floor(($(elem).width() -(state.size*state.zoom))/2)
      }); 
      // init pane
      $(elem).find('.pane').css({
        'left':state.mapX,
        'top':state.mapY,
        'right':state.mapX,
        'bottom':state.mapY
      });
      return state;
    },
    
    resize : function(img,elem,state,center) {
      if(state.angle%180 > 0 && $.browser.msie) {
        var margin = Math.abs(state.height-state.width)*state.zoom/2;
        $(img).css({  
          'width' :state.width*state.zoom,
          'height':state.height*state.zoom,     
          'top'   :(state.size-state.height)*state.zoom/2+margin,
          'left'  :(state.size-state.width) *state.zoom/2-margin
        });
      } else {
        $(img).css({
          'width' :state.width*state.zoom,
          'height':state.height*state.zoom,        
          'top'   :(state.size-state.height)*state.zoom/2,
          'left'  :(state.size-state.width) *state.zoom/2
        });
      }
      $('.drag-master',elem).css({        
        'width' :state.size*state.zoom,
        'height':state.size*state.zoom
      });
      
      methods.setDrag(img,elem,state);
      $('.zoom_level span',elem).html(Math.round(state.zoom*100)+"%");
      
      // CENTER
      if(center) {
        $(elem).find('.drag-master').css({
          'left':state.center[0]-(state.center[2]*state.zoom)-$(elem).offset().left,
          'top' :state.center[1]-(state.center[3]*state.zoom)-$(elem).offset().top
        });
      }
      
      // MAP
      var dm = $(elem).find('.drag-master');
      methods.mapDrag({position:{left:dm.offset().left-$(elem).offset().left,top:dm.offset().top-$(elem).offset().top}},elem,$(elem).find('.doc'),state);
    },
    
    mapDrag : function(ui,elem,pic,state) {
      $(pic).rotate(state.angle);      
            
      var width = state.mapWidth;
      var height = state.mapHeight;
      var borderWidth = ($.browser.msie)? 0:parseInt($(elem).find('.pane').css('border-left-width'));
      
      // map info
      var mapOffset = $(elem).find('.doc_map').offset();
      var mapImg = $(elem).find('.doc_map img');
      var miOffset = mapImg.offset();
      var miWidth = mapImg.width();
      var miHeight = mapImg.height();
      
      // display info
      var picWidth = $(pic).width();
      var picHeight = $(pic).height();
      
      // rotational dimension consideration
      if(!$.browser.msie && state.angle%180 > 0) {
        width = height;
        height = state.mapWidth;
        miWidth = miHeight;
        miHeight = mapImg.width();
        picWidth = picHeight;
        picHeight = $(pic).width();
				var diff = Math.abs(miWidth-miHeight)/2;
				miOffset.left += diff;
				miOffset.top  -= diff;
      }
      
      // position and width percents
      var DM = $('.drag-master');
      var mX = (picWidth-DM.width())/2;
      var mY = (picHeight-DM.height())/2;
      var posX = Math.min(0,(ui.position.left-mX))/picWidth;
      var posY = Math.min(0,(ui.position.top-mY))/picHeight;
      var coveredX = Math.max(0,picWidth-$(elem).width())/picWidth;
      var coveredY = Math.max(0,picHeight-$(elem).height())/picHeight;
      
      var css = {
          'left'  : miOffset.left - mapOffset.left - miWidth*posX - borderWidth,
          'top'   : miOffset.top - mapOffset.top - miHeight*posY - borderWidth,
          'width' : (miWidth - borderWidth) * (1-coveredX),
          'height': (miHeight - borderWidth) * (1-coveredY)
      };
      
      // firefox rotation fix (doesn't change offset)
      if(state.angle%180 > 0 && $.browser.mozilla) {
        var m = Math.abs(height-width)/2;
        css.left -= m;
        css.top  += m;
      }
      
      $(elem).find('.pane').css(css);
        
      // dragging
      var cont = [
        miOffset.left,
        miOffset.top,
        miOffset.left + miWidth - css.width - borderWidth*2,
        miOffset.top + miHeight - css.height - borderWidth*2
      ];
      
      // firefox rotation fix part 2
      if(state.angle%180 > 0 && $.browser.mozilla) {
        var m = Math.abs(height-width)/2;
        cont[0] -= m;
        cont[1] += m;
        cont[2] -= m;
        cont[3] += m;
      }
      
      $(elem).find('.pane').draggable({
        containment:cont,
        scroll:false,
        drag:function(event,ui) {
          var DM = $(elem).find('.drag-master');
          var margin = Math.abs(state.height-state.width)/2*state.zoom;
          var left = (~(ui.position.left-state.mapX)/width)*picWidth;
          var top = (~(ui.position.top-state.mapY)/height)*picHeight;
          if(state.width < state.height)
            left += mX + mY;
          else
            top += mX + mY;
          if($(elem).width() < $(pic).width())
            DM.css({'left': left });
          if($(elem).height() < $(pic).height())
            DM.css({'top' : top  });
        }
      });
    }
  };  
  
  $.fn.inspector  = function(src) {
    if($('.drag-master',this).length == 0 || $.browser.msie) { // init or IE just start over for IE 
      methods.init(this); // add interface
      methods.setHandlers(this, methods.load(this,src) );
    }
    else if($('.doc',this).attr('src') != src) {
      methods.setHandlers(this, methods.load(this,src) );
    }
    return this;
  }
})(jQuery);

/* CHANGELOG
 * ---------
 * Version 1.4          : 18 October 2011   // Bug fixes
 * Version 1.3          :  6 October 2011   // Map dragging
 * Version 1.2          :  6 October 2011   // IE rotation bug fixed
 * Version 1.1          :  5 October 2011   // Position map
 * Version 1.0          : 29 September 2011 // Now fully IE compatible.
 > - Release Candidate - 
 * Version 0.5   (rc7)  : 28 September 2011 // Now IE compatible.
 * Version 0.4.2 (rc6)  : 27 September 2011 // can no longer select zoom_level,
                                               can no longer scroll with dragging
 * Version 0.4.1 (rc5)  : 27 September 2011 // fixed loading error while rotated
 * Version 0.4   (rc4)  : 27 September 2011 // fixed multiple call bug
 * Version 0.3.2 (rc3)  : 27 September 2011 // added zoom buttons
 * Version 0.3.1 (rc2)  : 27 September 2011 // fixed zooming bug
 * Version 0.3   (rc1)  : 22 September 2011 // zooming bubble
 > - Beta -
 * Version 0.2          : 18 September 2011 // added rotation
 > - Alpha -
 * Version 0.1          : 12 September 2011 // initial design
 */