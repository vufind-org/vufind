function ChannelSlider(el) {
  return (function Slider() {
    // Elements
    var _container; // Entire element
    var _slider;    // Moving, too-wide element
    var _menu;
    var _leftbtn;
    var _rightbtn;
    var _scrollbar;
    // Data
    var _slidePositions;
    var _xpos = 0;
    var _maxpos = false;
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
      _menu = $('<nav class="slider-menu"></nav>');
      var group = $('<div class="btn-group pull-left"></div>');
      _leftbtn = $('<button class="btn btn-default" disabled><i class="fa fa-arrow-left"></i></button>').click(pageLeft);
      _rightbtn = $('<button class="btn btn-default"><i class="fa fa-arrow-right"></i></button>').click(pageRight);
      _scrollbar = $('<div class="scroll-bar"></div>');
      group.append(_leftbtn);
      group.append(_rightbtn);
      _menu.append(group);
      _menu.append(_scrollbar);
      _container.append(_menu);
    };

    var _adjustWidth = function _adjustWidth() {
      // Make the slider ridiculously wide
      _slider.css('width', 1e6);
      // Grab slide positions
      var slides = _slider.find('.slide');
      var farLeft = slides[0].getBoundingClientRect().left;
      _slidePositions = slides.map(function slidePositionsMap(i, op) {
        var box = op.getBoundingClientRect();
        if (i === 0) {
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
      _scrollbar.css('width', _container.width() / _slidePositions.length);
      _maxpos = _slidePositions[_slidePositions.length - 1].right - _container.width() + 10;
    };
    var _move = function _move(newpos) {
      _targetx = Math.max(0, Math.min(newpos, _maxpos));
      _animate();
      // If we're running into the end, we need a new current
      if (_targetx === _maxpos) {
        _leftbtn.removeAttr('disabled');
        _rightbtn.attr('disabled', 1);
        for (var i = 0; i < _slidePositions.length; i++) {
          if (_slidePositions[i].left >= _maxpos) {
            _current = i + 1;
            break;
          }
        }
      } else if (_targetx === 0) {
        _leftbtn.attr('disabled', 1);
        _rightbtn.removeAttr('disabled');
      } else {
        _leftbtn.removeAttr('disabled');
        _rightbtn.removeAttr('disabled');
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
      var barMax = _container.width() - _scrollbar.width();
      _scrollbar.css('left', Math.max(0, Math.min(barMax, (_xpos / _maxpos) * barMax)));
    };
    var _moveToClosest = function _moveToClosest(threshold) {
      for (var i = 0; i < _slidePositions.length; i++) {
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

    /* --- Setup --- */
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

    var _touchX = null;
    var _draggingScrollbar = false;
    var _sliderDragStart = function _sliderDragStart(e) {
      _touchX = e.clientX || e.originalEvent.touches[0].clientX;
      _draggingScrollbar = false;
    };
    var _scrollbarDragStart = function _scrollbarDragStart(e) {
      _touchX = e.clientX || e.originalEvent.touches[0].clientX;
      _draggingScrollbar = true;
    };
    var _sliderDragMove = function _sliderDrag(e) {
      if (_touchX === null) {
        return;
      }
      var x = e.clientX || e.originalEvent.touches[0].clientX;
      if (_draggingScrollbar) {
        _targetx = _maxpos * (x - _container.offset().left) / _container.width();
        _animate();
      } else {
        var diffX = _touchX - x;
        if (Math.abs(diffX) > 100) {
          if (diffX < 0) {
            pageLeft();
          } else {
            pageRight();
          }
          _touchX = null;
        }
      }
    };
    var _dragEnd = function _dragEnd() {
      _touchX = null;
      // Move to true closest
      if (_draggingScrollbar) {
        var mindist = Math.abs(_slidePositions[0].left - _xpos);
        var closest = 0;
        for (var i = 1; i < _slidePositions.length; i++) {
          var d = Math.abs(_slidePositions[i].left - _xpos);
          if (d < mindist) {
            mindist = d;
            closest = i;
          }
        }
        _move(_slidePositions[closest].left);
      }
    };
    _slider.on('mousedown', _sliderDragStart);
    _scrollbar.on('mousedown', _scrollbarDragStart);
    _slider.on('touchstart', _sliderDragStart);
    _slider.on('touchmove', _sliderDragMove);
    _container.on('mousemove', _sliderDragMove);
    $(document).on('mouseup', _dragEnd);
    $(document).on('touchend', _dragEnd);

    return true;
  })();
}
