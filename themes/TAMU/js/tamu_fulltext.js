$(document).ready(function() {
  let $sfxButton = $("#sfxButton");
  let title = $sfxButton.data("title");
  let issn = $sfxButton.data("issn");
  if (title && issn) {
    $.ajax({
      url: gifmBase+"catalog-access/check-full-text",
      data: {"title": title,"issn": issn}
    }).done(function(data) {
      if (data.payload.Boolean == true) {
        $("#sfxRow td").children().removeClass("hidden");
      }
    });
  }
});
