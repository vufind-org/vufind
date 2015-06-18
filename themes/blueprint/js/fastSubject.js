$(document).ready(function() {
	$('.relatedSubjects').hide();
	$( ".toggle-related-subjects" ).click(function() {
		if($(this).next().is(":visible")) {
			$( this ).text('+');
		}
		else {
			$( this ).text('-');
		}
	  	$( this ).next().toggle();
	});
});
