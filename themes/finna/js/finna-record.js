/*global VuFind,checkSaveStatuses*/
finna.record = (function() {
    var initDescription = function() {
        var description = $("#description_text");
        if (description.length) {
            var id = description.data('id');
            var url = VuFind.path + '/AJAX/JSON?method=getDescription&id=' + id;
            $.getJSON(url)
            .done(function(response) {
                if (response.data.length > 0) {
                    description.html(response.data);
                    description.wrapInner('<div class="truncate-field wide"><p class="summary"></p></div>');
                    finna.layout.initTruncate(description);
                } else {
                    description.hide();
                }
            })
            .fail(function() {
                description.hide();
            });
        }
    }

    getRequestLinkData = function(element, recordId) {
      var vars = {}, hash;
      var hashes = element.href.slice(element.href.indexOf('?') + 1).split('&');
    
      for (var i = 0; i < hashes.length; i++) {
        hash = hashes[i].split('=');
        var x = hash[0];
        var y = hash[1];
        vars[x] = y;
      }
      vars['id'] = recordId;
      return vars;
    }
    
    checkRequestsAreValid = function(elements, requestType) {
      if (!elements[0]) {
        return;
      }
      var recordId = elements[0].href.match(/\/Record\/([^\/]+)\//)[1];
      
      var vars = [];
      $.each(elements, function(idx, element) {
        vars.push(getRequestLinkData(element, recordId));  
      });
      
    
      var url = VuFind.path + '/AJAX/JSON?method=checkRequestsAreValid';
      $.ajax({
        dataType: 'json',
        data: {id: recordId, requestType: requestType, data: vars},
        method: 'POST',
        cache: false,
        url: url
      })
      .done(function(responses) {
        $.each(responses.data, function(idx, response) {
          var element = elements[idx];
          if (response.status) {
            $(element).removeClass('disabled')
              .html(response.msg);
            } else {
              $(element).remove();
            }
        });
      })
      .fail(function(response, textStatus) {
        console.log(response, textStatus);
      });
    }
    
    var setUpCheckRequest = function() {
      checkRequestsAreValid($('.expandedCheckRequest').removeClass('expandedCheckRequest'), 'Hold');
      checkRequestsAreValid($('.expandedCheckStorageRetrievalRequest').removeClass('expandedCheckStorageRetrievalRequest'), 'StorageRetrievalRequest');
      checkRequestsAreValid($('.expandedCheckILLRequest').removeClass('expandedCheckILLRequest'), 'ILLRequest');
    }
    
    var initHoldingsControls = function() {
        $('.holdings-container-heading').click(function (e) {
            if ($(e.target).hasClass('location-service') || $(e.target).parents().hasClass('location-service')) {
                return;
            }
            $(this).nextUntil('.holdings-container-heading').toggleClass('collapsed');
            if ($('.location .fa', this).hasClass('fa-arrow-down')) {
                $('.location .fa', this).removeClass('fa-arrow-down');
                $('.location .fa', this).addClass('fa-arrow-right'); 
            }
            else {
                $('.location .fa', this).removeClass('fa-arrow-right');
                $('.location .fa', this).addClass('fa-arrow-down');
                var rows = $(this).nextUntil('.holdings-container-heading');
                checkRequestsAreValid(rows.find('.collapsedCheckRequest').removeClass('collapsedCheckRequest'), 'Hold', 'holdBlocked');
                checkRequestsAreValid(rows.find('.collapsedCheckStorageRetrievalRequest').removeClass('collapsedCheckStorageRetrievalRequest'), 'StorageRetrievalRequest', 'StorageRetrievalRequestBlocked');
                checkRequestsAreValid(rows.find('.collapsedCheckILLRequest').removeClass('collapsedCheckILLRequest'), 'ILLRequest', 'ILLRequestBlocked');
            }
        });
    };

    var my = {
        checkRequestsAreValid: checkRequestsAreValid,
        init: function() {
            initDescription();
        },
        setupHoldingsTab: function() {
            initHoldingsControls();
            setUpCheckRequest();
            finna.layout.initLocationService();
            finna.layout.initJumpMenus($('.holdings-tab'));
            VuFind.lightbox.bind($('.holdings-tab'));
        }
    };

    return my;
})(finna);
