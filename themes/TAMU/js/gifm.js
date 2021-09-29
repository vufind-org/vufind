//check with Catalog Service
$(document).ready(function() {

	var bibId = $("#hiddenInstanceId").val();
	if (bibId) {
		if ($(".getit").length > 0) {
			$(".getit").each(function(index,button) {
				$(this).append("<div class=\"buttons\"><div class=\"gifm-loading\"></div><i class=\"updates\"></i></div>");
			});
			var messageIncrement = 0;
			var startTimer = function() {
        patience = setInterval(function() {
                      var messages = ["This title has a lot of volumes, so this may take a while.",
                                      "We're still working on creating the Get It For Me buttons.",
                                      "Please stay on the page. We're still working on it",
                                      "Please stay on the page while we retrieve the information."];
                      if (messageIncrement >= messages.length) {
                        messageIncrement = 0;
                      }
                      $(".getit div.buttons .updates").text(messages[messageIncrement]);
                      messageIncrement++;
                    }, 15000);
      };

			$.ajax({
				url: gifmBase+"catalog-access/get-buttons",
				data: {"bibId": bibId,"catalogName":catalogName},
				beforeSend: function() {
					startTimer();
				}
			}).done(function(data) {
				if (data.meta.status === "ERROR") {
					$(".getit div.buttons i").text(data.meta.message);
				} else {
					$(".getit .buttons").html("");
					$.each(data.payload.HashMap,function(mfhd,buttonPresentation) {
						$.each(buttonPresentation.buttons,function(index,button) {
							let buttonHtml = '<a target="_blank" class="'+button.cssClasses+'" href="https://'+button.linkHref+'">'+button.linkText+'</a>';
							$("#getit_"+button.itemKey+" .buttons").append(buttonHtml);
						});
					});
				}
			}).fail(function(xhr,status,error) {
				$(".getit div.buttons").html("<i>Error retrieving holding details</i>");
			}).always(function() {
				clearInterval(patience);
			});
		}
	}
});