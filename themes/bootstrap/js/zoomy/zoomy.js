(function($){
  var Zoomy = function(src, thumb) {
    var state,
    core = {
      load: function(src, thumb) {
        state = {
          'container':{
            'elem'   :$('.zoomy-container'),
            'width'  :$('.zoomy-container').width(),
            'height' :$('.zoomy-container').height(),
            'left'   :$('.zoomy-container').offset().left,
            'top'    :$('.zoomy-container').offset().top,
            'centerx':$('.zoomy-container').width()/2,
            'centery':$('.zoomy-container').height()/2
          },
          'page':{
            'elem'  :$('.zoomy-container .page'),
            'width' :$('.zoomy-container .page').width(),
            'height':$('.zoomy-container .page').height(),
            'theta' :0
          },
          'map':{
            'elem':$('.zoomy-container .map'),
            'size':120,
            'margin':60
          },
          'loading'    :$('.zoomy-container .loading-bar'),
          'bounds' :$('.zoomy-container .bounds'),
          'img'    :$('.zoomy-container .page img'),
          'minimap':$('.zoomy-container .mini_map'),
          'scope'  :$('.zoomy-container .scope'),
          'zoombar':$('.zoomy-container .zoom'),
          'zoomset':$('.zoomy-container .zoom .level'),
          'oldzoom':state ? state.page.zoom : undefined
        };
        // Load image into page and map
        state.img.bind('load', function() {
          // Fix width and height
          var img = new Image();
          img.src = state.img.attr('src');
          state.page.width  = img.width;
          state.page.height = img.height;
          state.loading.hide();
          if(state.page.width == 0 || state.page.height == 0) {
            state.page.width  = state.img[0].naturalWidth;
            state.page.height = state.img[0].naturalHeight;
          }
          //console.log(state.page.width);
          //console.log(state.page.height);
          state.page.minzoom = Math.min(
              state.page.width  < state.container.width
                ? 1.0 : (state.container.width-50) /state.page.width,
              state.page.height < state.container.height
                ? 1.0 : (state.container.height-50)/state.page.height
            );
          state.page.zoom = state.oldzoom ? state.oldzoom : state.page.minzoom;
          state.map.zoom = state.map.size/Math.max(state.page.width, state.page.height);
          state.map.width = Math.floor(state.page.width *state.map.zoom);
          state.map.height = Math.floor(state.page.height*state.map.zoom);
          var center = {
            'width' :state.page.zwidth  = state.page.width *state.page.zoom,
            'height':state.page.zheight = state.page.height*state.page.zoom,
            'left'  :(state.container.width  - state.page.zwidth) /2,
            'top'   :(state.container.height - state.page.zheight)/2
          };
          state.page.elem.css(center)                
            .draggable({containment:".zoomy-container .bounds",scroll:false,drag:core.moveMap})
            .unbind('mousewheel').bind('mousewheel', core.zoom);
          // Initial map size
          state.map.elem.css({
              'width' :state.map.width, 'height':state.map.height,
              'right' :(state.map.size+state.map.margin-state.map.width)/2,
              'bottom':(state.map.size+state.map.margin-state.map.height)/2
            });
          state.minimap.css({
              'width' :state.map.width,
              'height':state.map.height
            });
          if(navigator.userAgent.match(/iPad/i) == null) {
            state.zoombar.css({
                'bottom' :(state.map.size+state.map.margin-state.map.height)/2+state.map.height+3
              });
          }
          // Initialize map lens
          state.scope.css({
              'width' :state.map.width-2,
              'height':state.map.height-2,
              'top'   :0,
              'left'  :0
            })
            .draggable({containment:state.map.elem,scroll:false,drag:function(event,ui){
              var width = state.page.sideways ? state.page.zheight : state.page.zwidth;
              var left = width < state.container.width
                       ? (width-state.container.width)/2
                       : ($(this).offset().left-state.map.elem.offset().left-1)/state.map.zoom*state.page.zoom;
              var height = state.page.sideways ? state.page.zwidth : state.page.zheight;
              var top = height < state.container.height
                       ? (height-state.container.height)/2
                       : ($(this).offset().top -state.map.elem.offset().top -1)/state.map.zoom*state.page.zoom;            
              state.page.elem.css({
                'left':-left,
                'top' :-top
              });
            }});
          // Display
          state.map.elem.show();
          state.page.elem.show();
          $('.zoomy-container .zoom').show();
          $('.zoomy-container .control').show();
          state.loading.hide();
          core.rotate(360);
          core.zoom({init:state.page.zoom},0);
        });
        state.map.elem.hide();
        state.page.elem.hide();
        $('.zoomy-container .zoom').hide();
        $('.zoomy-container .control').hide();
        // Kick off loading
        if(state.bar) {
          state.loading.show();
        }
        state.loading.show();
        state.img.attr('src', src);
        // If we have a seperate thumbnail
        if(thumb) {
          state.minimap.attr('src', thumb);
        } else {
          state.minimap.attr('src', src);
        }
      },
      rotateLeft: function() {
        //drawCross(centerX+(centerY-mouseY)-boxHeight,centerY+(mouseX-centerX));
        core.rotate(state.page.theta + 90);
        var x = parseInt(state.page.elem.css('left'));
        var y = parseInt(state.page.elem.css('top'));
        var z = state.page.theta%180 ? state.page.zheight : state.page.zwidth
        var css = {
          left:state.container.centerx+(state.container.centery-y)-z,
          top :state.container.centery+(x-state.container.centerx)
        };
        state.page.elem.css(css);
        core.zoom({fake:true}, 0);
      },
      rotateRight: function() {
        //drawCross(centerX+(mouseY-centerY),centerY+(centerX-mouseX)-boxWidth);
        core.rotate(state.page.theta + 270);
        var x = parseInt(state.page.elem.css('left'));
        var y = parseInt(state.page.elem.css('top'));
        var z = state.page.theta%180 ? state.page.zwidth : state.page.zheight
        var css = {
          left:state.container.centerx-(state.container.centery-y),
          top :state.container.centery-(x-state.container.centerx)-z
        };
        state.page.elem.css(css);
        core.zoom({fake:true}, 0);
      },
      rotate: function(angle) {
        state.page.elem.css({
            'width' :state.page.zheight,
            'height':state.page.zwidth
          });
        state.img.css({
            'width' :state.page.zwidth,
            'height':state.page.zheight
          }).rotate(angle);
        state.page.sideways = angle % 180 == 90;
        // Rotate map
        if(state.page.sideways) {
          state.map.elem.css({
              'width' :state.map.height, 'height':state.map.width,
              'right' :(state.map.size+state.map.margin-state.map.height)/2,
              'bottom':(state.map.size+state.map.margin-state.map.width)/2-21
            });
        } else {
          state.map.elem.css({
              'width' :state.map.width, 'height':state.map.height,
              'right' :(state.map.size+state.map.margin-state.map.width)/2,
              'bottom':(state.map.size+state.map.margin-state.map.height)/2-21
            });
        }
        var moffset = state.page.sideways && $.browser != "msie"
                    ? Math.floor((state.map.height-state.map.width)/2)
                    : 0;
        state.minimap.css({
            'left':moffset,
            'top' :-moffset
          })
          .rotate(angle); //*/
        // Resize map lens
        state.page.theta = angle;
      },
      moveMap: function() {
        var left = state.page.zwidth > state.container.width
          ? 0-((state.page.elem.offset().left-state.container.left-4)/state.page.zoom*state.map.zoom)
          : 1;
        var top  = state.page.zheight > state.container.height
          ? 0-((state.page.elem.offset().top -state.container.top -4)/state.page.zoom*state.map.zoom)
          : 1;
        state.scope.css({
          'left':left < 0 ? 0 : left-1,
          'top' :top  < 0 ? 0 : top-1
        });
      },
      zoom: function(event, delta) {
        if(!event.fake && !event.setZoom && !event.init) event.preventDefault();
        if(event.init) {
          event.setZoom = event.init;
          event.fake = true;
        }
        if(event.setZoom || event.fake) {
          event.pageX = state.container.centerx+state.container.left;
          event.pageY = state.container.centery+state.container.top;
        }
        var nZ = event.setZoom != undefined ? event.setZoom : state.page.zoom*(1+(delta/4));
        // Keep in-bounds
        nZ = Math.max(state.page.minzoom, Math.min(8, nZ));
        if(nZ == state.page.zoom && !event.fake) return;
        // Original width/heigth
        var oW = state.page.width  * state.page.zoom;
        var oH = state.page.height * state.page.zoom;
        // New width/height
        var nW = state.page.width  * nZ;
        var nH = state.page.height * nZ;
        // New drag_bounds width/height
        var dW = Math.max(state.container.width , nW*2-state.container.width);
        var dH = Math.max(state.container.height, nH*2-state.container.height);
        var left = parseFloat($('.zoomy-container .page').css('left'));
        var top = parseFloat($('.zoomy-container .page').css('top'));
        if(state.page.sideways) {
          // Offset mouse position
          var oX = event.fake ? 0 : Math.floor(event.pageX-state.page.elem.offset().left)-Math.floor(oH/2);
          var oY = event.fake ? 0 : Math.floor(event.pageY-state.page.elem.offset().top) -Math.floor(oW/2);
          if(nZ == 8) {
            oX *= (nZ-state.page.zoom)/2;
            oY *= (nZ-state.page.zoom)/2;
          }
          // Resize and center page on mouse
          $('.zoomy-container .page').css({'width':nH, 'height':nW});
          left-=(nH-oH+(oX*delta/2))/2;
          top -=(nW-oW+(oY*delta/2))/2;
          // Resize map lens
          state.scope.css({
              'width' :Math.min(state.map.height,state.map.width *(state.container.width /nW))-2,
              'height':Math.min(state.map.width, state.map.height*(state.container.height/nH))-2
            });
          dW = Math.max(state.container.width , nH*2-state.container.width);
          dH = Math.max(state.container.height, nW*2-state.container.height);
        } else {
          // Offset mouse position
          var oX = event.fake ? 0 : Math.floor(event.pageX-state.page.elem.offset().left)-Math.floor(oW/2);
          var oY = event.fake ? 0 : Math.floor(event.pageY-state.page.elem.offset().top) -Math.floor(oH/2);
          if(nZ == 8) {
            oX *= (nZ-state.page.zoom)/2;
            oY *= (nZ-state.page.zoom)/2;
          }
          // Resize and center page on mouse
          $('.zoomy-container .page').css({'width':nW,'height':nH});
          left-=(nW-oW+(oX*delta/2))/2;
          top -=(nH-oH+(oY*delta/2))/2;
          // Resize map lens
          state.scope.css({
              'width' :Math.min(state.map.width-2, state.map.width *(state.container.width /nW)),
              'height':Math.min(state.map.height-2,state.map.height*(state.container.height/nH))
            });
        }
        state.page.zoom = nZ;
        state.page.zwidth = nW;
        state.page.zheight = nH;
        // Port fixing
        if(event.fake) {
          if(nW > state.container.width) {
            if(left+nW < state.container.width) {
              left = state.container.width-nW;
            } else if(left > 0) {
              left = 0;
            }
          }
          if(nH > state.container.height) {
            if(top+nH < state.container.height) {
              top = state.container.height-nH;
            } else if(top > 0) {
              top = 0;
            }
          }
        }
        state.page.elem.css({'left':left,'top':top});
        if(!(event.setZoom && !event.init)) {
          state.zoomset.val(Math.round(nZ*100));
        }
        // Resize and center bounds
        state.bounds.css({
            'width' :dW, 'height':dH,
            'left'  :(state.container.width -dW)/2,
            'top'   :(state.container.height-dH)/2
          });
        var offset = state.page.sideways
                   ? Math.floor((state.page.height-state.page.width)*state.page.zoom/2)
                   : 0;
        state.img.css({
            'left':offset, 'top' :-offset,
            'width' :state.page.width *state.page.zoom,
            'height':state.page.height*state.page.zoom
          });
        core.moveMap();
      },
      toggleUI: function() {
        $('.zoomy-container .ui').toggle();
        if($('.zoomy-container .ui').is(':hidden')) {
          $('.zoomy-container #toggle').html('<i class="icon-resize-full icon-rotate-90"></i>');
        } else {
          $('.zoomy-container #toggle').html('<i class="icon-resize-small icon-rotate-90"></i>');
        }
      }
    };
    
    core.load(src, thumb);
    
    $('.zoomy-container .turn-left').unbind('click').bind('click', core.rotateLeft);
    $('.zoomy-container .turn-right').unbind('click').bind('click', core.rotateRight);
    $('.zoomy-container .zoom-in').unbind('click').bind('click', function(){core.zoom({fake:1},1)});
    $('.zoomy-container .zoom-out').unbind('click').bind('click', function(){core.zoom({fake:1},-1)});
    $('.zoomy-container .zoom .level').unbind('keyup').bind('keyup', function(){core.zoom({setZoom:this.value/100},0)});
    $('.zoomy-container #toggle').unbind('click').bind('click', function(){core.toggleUI()});
    
    return {'src':src,'load':core.load};
  };
  
  $.fn.zoomy = function(src, thumb) {
    var elem = this,
        instance = elem.data('zoomy');            
    if(!instance) { // init
      $(this).html('<div class="bounds"></div><div class="page"><img src=""></div><div class="map ui"><img src="" class="mini_map"><div class="scope"></div></div><div class="loading-bar"><div class="progress progress-striped active" style="width:40%;margin:auto;border:1px solid #FFF"><div class="bar" style="width:100%;">Loading...</div></div></div><div class="control"><a class="turn-right ui"><span class="icon-stack"><i class="icon-sign-blank icon-muted icon-stack-base"></i><i class="icon-rotate-left"></i></span></i></a><a class="turn-left ui"><span class="icon-stack"><i class="icon-sign-blank icon-muted icon-stack-base"></i><i class="icon-rotate-right"></i></span></a><a id="toggle"><i class="icon-resize-small icon-rotate-90"></i></a></div><div class="zoom ui"><a class="zoom-out"><span class="icon-stack"><i class="icon-sign-blank icon-muted icon-stack-base"></i><i class="icon-plus"></i></span></a><input class="level">%<a class="zoom-in"><span class="icon-stack"><i class="icon-sign-blank icon-muted icon-stack-base"></i><i class="icon-minus"></i></span></a></div>');
      $(this).addClass('zoomy-container');
      instance = new Zoomy(src, thumb);
      elem.data('zoomy', instance);
    } else {
      if(instance['src'] != src) {
        instance['load'](src, thumb);
        instance['src'] = src;
        elem.data('zoomy', instance);
      }
    }
    return this;
  }
})(jQuery);
