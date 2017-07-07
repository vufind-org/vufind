function toggleResultChecked(box) {
  if (box.checked) {
    $(box).closest('.result,.grid-result').addClass('checked');
  } else {
    $(box).closest('.result,.grid-result').removeClass('checked');
  }
}

$(document).ready(function sandalTweaks() {
  var boxes = $('.record-checkbox input,.grid-checkbox input');
  for (var i = 0; i < boxes.length; i++) {
    $(boxes[i]).change(function toggleChecked(e) {
      toggleResultChecked(e.target);
    });
    toggleResultChecked(boxes[i]);
  }
  $('.checkbox-select-all').change(function toggleAllCheckboxes() {
    var subboxes = $('.record-checkbox input,.grid-checkbox input');
    for (var j = 0; j < boxes.length; j++) {
      toggleResultChecked(subboxes[j]);
    }
  });
});
