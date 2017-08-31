// Highlight entire element when checked
function toggleResultChecked(box) {
  if (box.checked) {
    $(box).closest('.result,.grid-result').addClass('checked');
  } else {
    $(box).closest('.result,.grid-result').removeClass('checked');
  }
}
function bindModernCheckboxes(_container) {
  var $container = typeof _container === 'undefined' ? $(document) : $(_container);
  var boxes = $container.find('.record-checkbox input,.grid-checkbox input');
  for (var i = 0; i < boxes.length; i++) {
    $(boxes[i]).change(function toggleChecked(e) {
      toggleResultChecked(e.target);
    });
    toggleResultChecked(boxes[i]);
  }
  $container.find('.checkbox-select-all').change(function toggleAllCheckboxes() {
    var subboxes = $('.record-checkbox input,.grid-checkbox input');
    for (var j = 0; j < boxes.length; j++) {
      toggleResultChecked(subboxes[j]);
    }
  });
}

VuFind.listen('vf-combined-ajax', function modernCombinedCheckboxes(e) {
  bindModernCheckboxes(e.detail);
});

$(document).ready(function modernTweaks() {
  bindModernCheckboxes();
});
