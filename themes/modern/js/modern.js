function toggleResultChecked(box) {
  if (box.checked) {
    $(box).closest('.result,.grid-result').addClass('checked');
  } else {
    $(box).closest('.result,.grid-result').removeClass('checked');
  }
}

$(document).ready(function modernTweaks() {
  var boxes = $('.record-checkbox input,.grid-checkbox input');
  for (var i = 0; i < boxes.length; i++) {
    $(boxes[i]).change(function toggleChecked(e) {
      console.log('change');
      toggleResultChecked(e.target);
    });
    toggleResultChecked(boxes[i]);
  }
  $('.checkbox-select-all').change(function toggleAllCheckboxes() {
    var boxes = $('.record-checkbox input');
    for (var i = 0; i < boxes.length; i++) {
      toggleResultChecked(boxes[i]);
    }
  });
});
