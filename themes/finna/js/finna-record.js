/*global VuFind,checkSaveStatuses*/
finna.record = (function() {
    var initDescription = function() {
        var description = $("#description_text");
        if (description.length) {
            var id = description.data('id');
            var url = VuFind.getPath() + '/AJAX/JSON?method=getDescription&id=' + id;
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
      
    
      var url = VuFind.getPath() + '/AJAX/JSON?method=checkRequestsAreValid';
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
        $('.holdings-container-heading').click(function () {
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
        // Login link
        $('a.login-link').click(function() {
          return Lightbox.get('MyResearch','UserLogin');
        });
    };

    var initLocationService = function() {
        var closeModalCallback = function(modal) {
            modal.removeClass('location-service location-service-qrcode');
            modal.find('.modal-dialog').removeClass('modal-lg');
        };

        $('.location-service.location-service-modal').on('click', function() {
            var modal = $('#modal');
            modal.addClass('location-service');
            modal.find('.modal-dialog').addClass('modal-lg');
            modal.find('.modal-title').html(VuFind.translate('location-service'));
            Lightbox.titleSet = true;

            $('#modal').one('hidden.bs.modal', function() {
                closeModalCallback($(this));
            });
            var params = {
                source: $('.hiddenId').val().split('.')[0],
                callnumber: $(this).attr('data-callnumber'),
                collection: $(this).attr('data-collection')
            };
            return Lightbox.get('LocationService', 'modal', params);
        });

        $('.location-service.fa-qrcode').on('click', function() {
            var modal = $('#modal');
            modal.addClass('location-service-qrcode');
            modal.find('.modal-title').html(VuFind.translate('location-service'));
            Lightbox.titleSet = true;
            Lightbox.changeContent('');
            modal.find('.modal-body').qrcode({
                render: 'div',
                size: $(window).width() < 768 ? 240 : 300,
                text: $(this).prev('a.location-service').attr('href')
            });
            modal.modal();

            $('#modal').one('hidden.bs.modal', function() {
                closeModalCallback($(this));
            });
            return false;
        });
    };

    var initMobileModals = function() {
      var id = $('.hiddenId')[0].value;
      $('.cite-record-mobile').click(function() {
        var params = extractClassParams(this);
        return Lightbox.get(params['controller'], 'Cite', {id:id});
      });
      // Mail lightbox
      $('.mail-record-mobile').click(function() {
        var params = extractClassParams(this);
        return Lightbox.get(params['controller'], 'Email', {id:id});
      });
      // Save lightbox
      $('.save-record-mobile').click(function() {
        var params = extractClassParams(this);
        return Lightbox.get(params['controller'], 'Save', {id:id});
      });
    };

    // Override recordDocReady so that we can hook up our own saveRecord callback
    var origRecordDocReady = recordDocReady;
    recordDocReady = function() {
      origRecordDocReady();
      Lightbox.addFormCallback('saveRecord', function(html) {
        Lightbox.close();
        checkSaveStatuses();
        refreshTagList();
      });
    }
    
    var my = {
        checkRequestsAreValid: checkRequestsAreValid,
        init: function() {
            initDescription();
            finna.layout.initRecordFeedbackForm();
            initMobileModals();
        },
        setupHoldingsTab: function() {
            initHoldingsControls();
            setUpCheckRequest();
            initLocationService();
            finna.layout.initJumpMenus($('.holdings-tab'));
            finna.layout.initLightbox($('.holdings-tab'));
        }
    };

    return my;
})(finna);
