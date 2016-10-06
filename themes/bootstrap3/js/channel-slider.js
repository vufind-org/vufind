VuFind.register('slider', function VuFindSlider() {
  return function SliderFactory(el) {
    return (function Slider() {
      var _container; // Entire element
      var _slider;    // Moving, too-wide element
      var _slidePositions;
      var _xpos = 0;
      var _targetx = 0;
      var _current = 0;

      var _setupWrappers = function _setupWrappers() {
        var bounds = $('<div class="slider-bounds"></div>');
        _slider = $('<div class="slider-screen"></div>');
        _container.children().appendTo(_slider);
        bounds.append(_slider);
        _container.append(bounds);
      };
      var _addMenu = function _addMenu() {
        var menu = $('<nav class="slider-menu"></nav>');
        var group = $('<div class="btn-group"></div>');
        var leftbtn = $('<button class="btn btn-default"><i class="fa fa-arrow-left"></i></button>');
        var rightbtn = $('<button class="btn btn-default"><i class="fa fa-arrow-right"></i></button>');
        leftbtn.click(pageLeft);
        rightbtn.click(pageRight);
        group.append(leftbtn);
        group.append(rightbtn);
        menu.append(group);
        _container.append(menu);
      };

      var _adjustWidth = function _adjustWidth() {
        // Make the slider ridiculously wide
        _slider.css('width', 1e6);
        // Grab slide positions
        var slides = _slider.find('.slide');
        var farLeft = slides[0].getBoundingClientRect().left;
        _slidePositions = slides.map(function(i, op) {
          var box = op.getBoundingClientRect();
          if (i == 0) {
            return {
              left: 0,
              right: box.width
            };
          }
          return {
            left: box.left - farLeft,
            right: box.right - farLeft
          }
        });
        // Reign it in
        _slider.css('width', _slidePositions[_slidePositions.length - 1].right + 100);
      };
      var _move = function _move(newpos) {
        var maxpos =_slidePositions[_slidePositions.length - 1].right - _container.width() + 10;
        _targetx = Math.max(0, Math.min(newpos, maxpos));
        _animate();
        // If we're running into the end, we need a new current
        if (_targetx == maxpos) {
          for (var i = 0; i < _slidePositions.length; i++) {
            if (_slidePositions[i].left >= maxpos) {
              _current = i + 1;
              break;
            }
          }
        }
      };
      var _animate = function _animate() {
        _xpos += (_targetx - _xpos) / 10;
        if (Math.abs(_xpos - _targetx) < 1) {
          _xpos = _targetx;
        } else {
          requestAnimationFrame(_animate);
        }
        _slider.css('left', 0 - Math.round(_xpos));
      };
      var _moveToClosest = function _moveToClosest(threshold) {
        for (var i=0; i<_slidePositions.length; i++) {
          if (_slidePositions[i].right >= threshold) {
            _current = i;
            _move(_slidePositions[i].left);
            break;
          }
        }
      };
      var pageLeft = function pageLeft() {
        _moveToClosest(_slidePositions[_current].right - _container.width());
      };
      var pageRight = function pageRight() {
        _moveToClosest(_xpos + _container.width());
      };

      if (typeof el.dataset.slider !== 'undefined') {
        return false;
      }
      _container = $(el);
      _container.attr('data-slider', true);
      _setupWrappers();
      _addMenu();
      // Image loading listeners
      _slider.find('img').on('load', _adjustWidth);
      // Adjust width
      _adjustWidth();

      var _touchX =null;
      var _diffX;
      var _sliderDragStart = function _sliderDragStart(e) {
        _touchX = e.clientX || e.originalEvent.touches[0].clientX;
      };
      var _sliderDragMove = function _sliderDrag(e) {
        if (_touchX === null) {
          return;
        }
        var x = e.clientX || e.originalEvent.touches[0].clientX;
        var diffX = _touchX - x;
        if (Math.abs(diffX) > 100) {
          if (diffX < 0) {
            pageLeft();
          } else {
            pageRight();
          }
          _touchX = null;
        }
      };
      _slider.on('mousedown', _sliderDragStart);
      _slider.on('mousemove', _sliderDragMove);
      _slider.on('touchstart', _sliderDragStart);
      _slider.on('touchmove', _sliderDragMove);

      return true;
    })();
  }
});
