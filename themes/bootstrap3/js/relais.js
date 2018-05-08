/*global VuFind*/
/*exported isItemAvailableThroughPalci, relaisAddItem*/
//VUFIND/EZBORROW INTEGRATION

/*
Lightbox.addOpenAction(function() {
  //REMOVE THIS?
  //var serverResponse = event.srcElement.responseText;
  //var icon = "spinning";
  //alert(icon);
  //if (serverResponse.indexOf(icon) !== -1) {
  //  relaisRecordClickedFunction();
  //}
  //if ((serverResponse.indexOf(icon) !== -1) {
 //  startPalciRequest();
 // }
});

Lightbox.addCloseAction(function() {
$('#relaisRecordButton').click(startPalciRequest);
});
*/
//IF ITEM IS AVAILABLE -- START REQUEST
//OTHERWISE, OPEN EZ BORROW PAGE IN A DIFFERENT TAB
function startPalciRequest() {
  relaisRecordClickedFunction();
}

function isItemAvailableThroughPalci() {
  //IF THERE IS NO OPTION TO REQUEST
  //THROUGH PALCI ON THIS PAGE (E.G. ITS AVAILABLE)
  //THAN NO NEED TO CHECK FOR PALCI AVAILABILITY
  //8/30/2016
  if (!$('.palciLink').length) {
    return false;
  }
  //IS THIS ITEM AVAILABLE VIA EZBORROW
  var recordId = $('#record_id').val();
  //var recordSource = $('.hiddenSource').val();
  var oclc = $('#oclcid').val();
  var avail = false;
  var url = VuFind.path + '/AJAX/JSON?' + $.param({
    method: 'relaisAvailability',
    id: recordId,
    'oclcNumber': oclc
  });
  $.ajax({
    dataType: 'json',
    url: url,
    success: function relaisAvailabilitySuccessCallback(response) {
      if (response.data === "ok") {
        avail = true;
        $("span[class='palciLink']").each(function relaisLinkFormatter(index) {
          //console.log( index + ": " + $( this ).text() + ":" + avail);
          $(this).html('<a id="relaisRecordButton" href="#" class="modal-link"  href="#"  title="PALCI Request">PALCI Request (fastest)</a>&nbsp;&nbsp;');
          $('#relaisRecordButton').click(startPalciRequest);
        });
      }
      if (response.data === "no") {
        avail = false;
      }
    },
    error: function relaisAvailabilityErrorCallback() {
      avail = false;
    }
  });
  return avail;
}

//MAKE THE REQUEST FOR THE ITEM
//CHECKS TO SEE IF PATRON IS SIGNED IN
function relaisRecordClickedFunction() {
  //var id = $('.hiddenId')[0].value;
  var theUrl = $("#relaisRecordUrl").val();
  //var parts = theUrl.split('/');

  return VuFind.lightbox.ajax(theUrl);
}

function calcelPalciRequestOnClick() {
  $('#modal').modal('hide'); // hide the modal 
  $('#modal-dynamic-content').empty(); // empties dynamic content
  $('.modal-backdrop').remove(); // removes all modal-backdrops
}

function makeRelaisRequest(url) {
  $('#requestButton').html("<button class='btn btn-lg btn-info'><span class='glyphicon glyphicon-refresh spinning'></span> Requesting... </button>");
  //alert(url);
  $.ajax({
    dataType: 'json',
    url: url,
    success: function relaisRequestSuccessCallback(response) {
      status = response.status;
      var obj = jQuery.parseJSON(response.data);
      //alert("in success");
      if (status === "ERROR") {
        $('#requestButton').html("<input class='btn btn-primary' data-dismiss='modal' id='cancelPalciRequest' type='submit' value='Close'>");
        $('#requestMessage').html("<br><h4><b>There was a problem with this request.  Click <a href='https://library.lehigh.edu/content/e_zborrow_authentication' target='new'>here to request this item using the EZBorrow Website.</a></b></h4>");
      } else {
        $('#requestButton').html("<input class='btn btn-primary' data-dismiss='modal' id='cancelPalciRequest' type='submit' value='Close'>");
        $('#requestMessage').html("<br><h4><b>Confirmation:</b> Request id #" + obj.RequestNumber + " was created.  You will receive a confirmation email.<h4>");
      }
      $('#cancelPalciRequest').click(calcelPalciRequestOnClick);
    },
    error: function relaisRequestErrorCallback() {
     //alert("error");
    }
  });
}

function relaisAddItem() {
  //alert("click function called");
  //var id = $('.hiddenId')[0].value;
  //var parts = this.href.split('/');
  //var theUrl = $("#relaisRecordUrl").val();
  //var parts = theUrl.split('/');
  $('[data-dismiss="modal"]').on('click', function dismissReliasModalOnClick(){
    $('.modal').hide();
    $('.modal-backdrop').hide();
  });

  var recordId = $('#record_id').val();
  //var recordSource = $('.hiddenSource').val();
  var oclc = $('#oclcid').val();
  //alert(oclc);
  var url = VuFind.path + '/AJAX/JSON?' + $.param({
    method: 'relaisInfo',
    id: recordId,
    'oclcNumber': oclc
  });
  //alert("calling...");
  //AJAX CALL
  $.ajax({
    dataType: 'json',
    url: url,
    success: function relaisInfoSuccessCallback(response) {
      var obj = jQuery.parseJSON(response.data);
      //alert("in success getRelaisinfo");
      if (obj.Available) {
        $('#requestMessage').html("<h3>This item is available through PALCI.  Would you like to request it?</h3>");
        $('#relaisResults').html("");
        //$('#requestButton').html("<input class='btn btn-primary' type='submit' value='" + <?=$this->transEsc('Savezzz')?> +"/>");
        $('#requestButton').html("<input class='btn btn-primary' id='makePalciRequest' type='submit' value='Submit Request'>"
                                 + "<input class='btn btn-primary' data-dismiss='modal' id='cancelPalciRequest' type='submit' value='Cancel'>");
        $('#makePalciRequest').click(function makePalciRequestOnClick() {
          var orderUrl = VuFind.path + '/AJAX/JSON?' + $.param({
            method: 'relaisOrder',
            id: recordId,
            'oclcNumber': oclc
          });
          makeRelaisRequest(orderUrl);
        });
        //http://stackoverflow.com/questions/18279393/bootstrap-3-modal-doesnt-close-after-first-opening
        $('#cancelPalciRequest').click(calcelPalciRequestOnClick);
      } else {
        $('#relaisResults').html("");
        $('#requestButton').html("<input class='btn btn-primary' data-dismiss='modal' id='cancelPalciRequest' type='submit' value='Close'>");
        $('#requestMessage').html("<br><h4><b>There was a problem with this request.  Click <a href='https://library.lehigh.edu/content/e_zborrow_authentication' target='new'>here to request this item using the EZBorrow Website.</a></b></h4>");
        $('#cancelPalciRequest').click(calcelPalciRequestOnClick);
      }
    },
    error: function relaisInfoErrorCallback() {
      $('#relaisResults').html("");
      $('#requestButton').html("<input class='btn btn-primary' data-dismiss='modal' id='cancelPalciRequest' type='submit' value='Close'>");
      $('#requestMessage').html("<br><h4><b>There was a problem with this request.  Click <a href='https://library.lehigh.edu/content/e_zborrow_authentication' target='new'>here to request this item using the EZBorrow Website.</a></b></h4>");
      $('#cancelPalciRequest').click(calcelPalciRequestOnClick);
    }
  });
}
