/* global finna */

finna.multiSelect = (function multiSelect(){
  var option = '<li class="option" role="option" aria-selected="false"></li>';
  var hierarchy = '<span aria-hidden="true"></span>';
  var i = 0;
  var regExp = new RegExp(/[a-öA-Ö0-9-_ ]/);

  function MultiSelect(select, id) {
    var _ = this;
    _.id = id;
    _.select = $(select);
    _.select.hide();
    _.ul = $(select).siblings('ul.done').first();
    _.searchField = $(select).siblings('input.search').first();
    _.deleteButton = $(select).siblings('button.clear').first();
    _.words = [];
    _.wordCache = [];
    _.charCache = "";
    _.wasClicked = false;
    _.active = null;
    _.createList();
  }

  MultiSelect.prototype.createList = function createList() {
    var _ = this;
    var k = 0;
    var reg = /&nbsp;/g;
    var mark = '&nbsp;';

    _.select.children('option').each(function createUl(){
      $(this).attr('data-id', k);
      var optionClone = $(option).clone();
      var isParent = $(this).hasClass('option-parent');
      var isChild = $(this).hasClass('option-child');
      var spaces = $(this).html().match(reg);
      if (spaces !== null) {
        spaces = spaces.length;
      }
      var formattedHtml = $(this).html().replace(reg, '').toLowerCase();

      optionClone.attr({
        'data-target': k, 
        'id': _.id + '_opt_' + k++, 
        'aria-selected': $(this).prop('selected'),
        'data-formatted': formattedHtml
      });
      optionClone.html(mark.repeat(spaces) + '<span class="value">' + formattedHtml + '</span>');
      if (isParent) {
        optionClone.addClass('option-parent');
      }
      if (isChild) {
        var hierarchyClone = $(hierarchy).clone();
        hierarchyClone.attr('class', $(this).attr('class')).addClass('hierarchy-line');
        optionClone.prepend(hierarchyClone);
      }
      _.words.push(optionClone);
      _.ul.append(optionClone);
    });
    _.setEvents();
  };

  MultiSelect.prototype.setEvents = function setEvents() {
    var _ = this;
    _.ul.on('mousedown', function preventFocus(e) {
      e.preventDefault();
      e.stopPropagation();
      _.wasClicked = true;
      $(this).focus();
    });
    _.ul.on('touchstart', function preventFocus(e) {
      e.stopPropagation();
      _.wasClicked = true;
      $(this).focus();
    });
    _.ul.on('focusin', function setFirstActive() {
      if (_.wasClicked) {
        _.wasClicked = false;
        return;
      }

      if (_.active === null) {
        _.setActive($(this).find('.option:visible').first());
        _.scrollList(true);
      }
    });
    _.ul.children('.option').on('click', function setActiveClick() {
      _.setActive($(this));
      _.setSelected();
    });
    _.ul.on('focusout', function clearState() {
      _.clearActives();
      _.clearCaches();
    });
    _.ul.on('keyup', function charMatches(e) {
      e.preventDefault();
      var keyLower = e.key.toLowerCase();
      if (regExp.test(keyLower) === false) {
        return;
      }

      if (_.charCache !== keyLower) {
        _.clearCaches();
      }

      var hasActive = _.active.data('formatted').substring(0, 1) === keyLower;

      if (_.wordCache.length === 0) {
        $.each(_.words, function appendToUl(_i, val) {
          var char = val.data('formatted').substring(0, 1);
          if (char === keyLower && val.is(':visible')) {
            _.wordCache.push(val);
          }
        });
      }

      if (_.wordCache.length === 0) {
        return;
      }

      if (hasActive === false) {
        _.clearActives();
        _.setActive(_.wordCache[0]);
        _.scrollList(true);
      } else {
        var oldId = null;
        $.each(_.wordCache, function getNextActive(_i, val){
          if (val.hasClass('active')) {
            oldId = _i + 1;
          }

          if (oldId === _i) {
            _.setActive(val);
            _.scrollList(true);
            return false;
          }

          if (oldId === _.wordCache.length) {
            _.setActive(_.wordCache[0]);
            _.scrollList(true);
          }
        });
      }
      _.charCache = keyLower;

      if (e.key !== 'Enter' && e.key !== ' ') {
        return;
      }

      _.setSelected();
    });
    _.ul.on('keydown', function scrollArea(e) {
      if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown' && e.key !== 'Enter' && e.key !== ' ') {
        return;
      }
      e.preventDefault();

      if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
        var found = null;

        if (e.key === 'ArrowUp') {
          found = _.active.prevAll('.option:visible').first();
        } else if (e.key === 'ArrowDown') {
          found = _.active.nextAll('.option:visible').first();
        }
  
        if (found.length) {
          _.setActive(found);
          _.scrollList(false);
        }
      } else {
        _.setSelected();
      }
    });
    _.deleteButton.on('click', function clearSelections() {
      _.ul.children('[aria-selected=true]').each(function clearAll() {
        $(this).attr('aria-selected', false);
      });
      _.select.children('option:selected').each(function clearAll() {
        $(this).prop('selected', false);
      });
    });
    _.searchField.on('keyup', function filterOptions() {
      if (_.wordCache.length !== 0) {
        _.clearCaches();
      }
      var curVal = $(this).val().toLowerCase();
      if (curVal.length === 0) {
        _.ul.children().show();
      } else {
        _.ul.children().each(function setVisible() {
          var hierarchyLine = $(this).has('.hierarchy-line');
          if (String($(this).data('formatted')).indexOf(curVal) !== -1) {
            $(this).show();
          } else {
            $(this).hide();
          }
          if (hierarchyLine.length !== 0) {
            var parent = $(this).prevAll('.option-parent').first();
            if (parent.is(':hidden') && $(this).is(':visible')) {
              parent.show();
            }
          }
        });
      }
    });
  };

  MultiSelect.prototype.scrollList = function scrollList(clipTo) {
    var _ = this;
    var top = _.active.position().top;
    
    if (typeof clipTo !== 'undefined' && clipTo === true) {
      _.ul.scrollTop(_.ul.scrollTop() + _.active.position().top);
      return;
    }

    if (top + _.active.height() < _.active.height() - 5) {
      _.ul.scrollTop(_.ul.scrollTop() - _.ul.height());
    } else if (top >= _.ul.height() - _.active.height()) {
      _.ul.scrollTop(top + _.ul.scrollTop());
    }
  };

  MultiSelect.prototype.clearCaches = function clearCaches() {
    var _ = this;
    _.wordCache = [];
    _.charCache = "";
  };

  MultiSelect.prototype.clearActives = function clearActives() {
    var _ = this;
    _.ul.attr('aria-activedescendant', '');
    _.ul.children('.option').removeClass('active');
    _.active = null;
  };

  MultiSelect.prototype.setActive = function setActive(element) {
    var _ = this;
    _.clearActives();
    _.active = $(element);
    _.active.addClass('active');
    _.ul.attr('aria-activedescendant', _.active.attr('id'));
  };

  MultiSelect.prototype.setSelected = function setSelected() {
    var _ = this;
    var original = _.select.find('option[data-id=' + _.active.data('target') + ']');
    var isSelected = original.prop('selected');
    original.prop('selected', !isSelected);
    _.active.attr('aria-selected', !isSelected);
  };

  function init() {
    $('.finna-multiselect.init').each(function createMultiSelect(){
      new MultiSelect(this, i++);
    });
  }

  var my = {
    init: init
  };

  return my;
}());
