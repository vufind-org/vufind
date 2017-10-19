/*global finna */
finna.primoAdvSearch = (function finnaPrimoAdvSearch() {
  function initForm() {
    $('.primo-add-search').click(function onClickAddSearch() {
      var fieldCount = $('.primo-advanced-search-fields').length;
      var last = $('.primo-advanced-search-fields').last();
      var newField = last.clone();
      $.each(['input', 'select'], function handleField(ind, el) {
        var element = newField.find(el);
        var newId = element.attr('id');
        newId = newId.substr(0, newId.lastIndexOf('_') + 1) + fieldCount;
        element.attr('id', newId);
        if (el === 'input') {
          element.val('');
        }
      });
      last.after(newField);

      return false;
    });
  }

  var my = {
    init: function init() {
      initForm();
    }
  };

  return my;
})();
