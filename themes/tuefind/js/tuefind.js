var TueFind = {
    // helper function to add content anchors to content page links
    AddContentAnchors: function() {
        let current_anchor = null;
        if (window.location.href.includes('#')) {
            current_anchor = window.location.href.split('#').pop();
        }

        $('a').each(function() {
            let href = $(this).attr('href');
            if (href != undefined && !href.includes('#') && $(".not-anchor").length == 0) {
                if (href.match(/\/Content\//) || href.match(/\?subpage=/)) {
                    if (href.match(/[?&]lng=/)) {
                        // when switching the language, we want to keep the current anchor
                        this.setAttribute('href', href + '#' + current_anchor);
                    } else {
                        this.setAttribute('href', href + '#content');
                    }
                }
            }
        });
    },

    GetSearchboxSearchContext: function() {
        if ($("#searchForm").length > 0)
            return $("#searchForm").attr('action').replace(/\/(.*)\/Results/, "$1").toLowerCase();
        else
            return null;
    },

    AdjustSearchHandlers: function() {
        // Make sure that a selection of the search handler is transparently adjusted for a potential reload
        // i.e. when changing the sort order
        let search_context = TueFind.GetSearchboxSearchContext();
        if (search_context === null)
            return;

        let saved_search_handler = sessionStorage.getItem("tuefind_saved_search_handler_" + search_context);
        if (saved_search_handler != null) {
            $("#searchForm_type option:selected").removeAttr('selected');
            $("#searchForm_type").val(saved_search_handler);
            // We also have to set the handler explicitly adjusting the selection
            $("#searchForm_type [value=" + saved_search_handler + "]").attr('selected', 'selected');
            // Make sure the type is adjusted for the next form submission
            $('[name=type]:not([data-type-protected="1"])').val(saved_search_handler);
        }
        $("#searchForm_type").on('focus', function () {
            previous_search_handler = this.value;
        }).change(function adjustSearchHandler(e) {
            current_search_handler = $("#searchForm_type").val();
            sessionStorage.setItem("tuefind_saved_search_handler_" + search_context, current_search_handler);
            $("#searchForm_type [value=" + previous_search_handler + "]").removeAttr('selected');
            $("#searchForm_type [value=" + current_search_handler + "]").attr('selected', 'selected');
            $('[name=type]:not([data-type-protected="1"])').val(current_search_handler);
        });
    },

    ResetSearchHandlers: function() {
        sessionStorage.removeItem("tuefind_saved_search_handler_search");
        sessionStorage.removeItem("tuefind_saved_search_handler_search2");
    },

    EscapeHTML: function(text) {
        return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    },

    FormatTextType: function(text_type, verbose, types) {
        // Suppress type tagging in Item Search view if only one text type is active
        if (verbose && !types.includes(','))
            return '';
        return '<span class="label label-primary pull-right snippet-text-type">' + text_type + '</span>';
    },

    FormatPageInformation: function(page) {
        return '[' + page + ']';
    },

    ItemFulltextLink : function(doc_id, query, scope) {
        return '<div class="text-right snippets-end-vspace"><a class="btn btn-warning btn-sm" href="' + VuFind.path + '/Record/' + doc_id + '?fulltextquery=' + encodeURIComponent(query)
                                                                                 +'&fulltextscope=' + (scope ? encodeURIComponent(scope) : '')  + '#fulltextsearch">' +
                 VuFind.translate('All Matches') + '</a></div><br/>';
    },

    GetNoMatchesMessage(doc_id) {
        return '<div id="snippets_' + doc_id + '">' +  VuFind.translate('No Matches') + '</div>';
    },

    GetFulltextSnippets: function(url, doc_id, query, verbose = false, synonyms = "", fulltext_types = "") {
        var valid_synonym_terms = new RegExp('lang|all');
        synonyms = synonyms.match(valid_synonym_terms) ? synonyms : false;
        $.ajax({
            type: "GET",
            url: url + "fulltextsnippetproxy/load?search_query=" + query + "&doc_id=" + doc_id + (verbose ? "&verbose=1" : "")
                                                                 + (synonyms ? "&synonyms=" + synonyms : "")
                                                                 + (fulltext_types ? "&fulltext_types=" + fulltext_types : ""),
            dataType: "json",
            success: function (json) {
                $(document).ready(function () {
                    var snippets = json['snippets'];
                    $("#snippet_place_holder_" + doc_id).each(function () {
                        if (snippets)
                            $(this).replaceWith('<div id="snippets_' + doc_id + '" class="snippet-div">' + snippets.join('<br/>') + '<br/></div>');
                        else if (verbose)
                            $(this).replaceWith(TueFind.GetNoMatchesMessage(doc_id));
                        else
                            $(this).replaceWith();
                    });
                    if (snippets)
                        $(this).removeAttr('style');
                    $("#snippets_" + doc_id).each(function () {
                        if (snippets) {
                            let styles = snippets.map(a => (a.hasOwnProperty('style') ? a.style : null )).filter(Boolean).join();
                            $(styles).appendTo("head");
                            let snippets_and_pages = snippets.map(a => a.snippet +
                                                                 (a.hasOwnProperty('page') ? '<br/>' + TueFind.FormatPageInformation(a.page) : '') +
                                                                 TueFind.FormatTextType(a.text_type, verbose, fulltext_types));
                            $(this).html(snippets_and_pages.join('<hr class="snippet-separator"/>'));
                        } else if (verbose)
                            $(this).replaceWith(TueFind.GetNoMatchesMessage(doc_id));
                        else
                            $(this).html("");
                    });
                    $("[id^=snippets_] > p").each(function () { this.style.transform="none"; });
                    if (!verbose && snippets)
                        $("#snippets_" + doc_id).after(TueFind.ItemFulltextLink(doc_id, query, synonyms));
                });
            }, // end success
            error: function (xhr, ajaxOptions, thrownError) {
                $("#snippet_place_holder").each(function () {
                    $(this).replaceWith('Invalid server response!!!!!');
                })
            }
        });
    },

    GetBeaconReferencesFromFindbuch: function() {
        $('.tf-findbuch-references').each(function() {
            var container = this;
            var proxyUrl = this.getAttribute('data-url');
            var headline = this.getAttribute('data-headline');
            var sortBottomPattern = this.getAttribute('data-sort-bottom-pattern');
            var sortBottomRegex = new RegExp(sortBottomPattern);
            var filterUniquePattern = this.getAttribute('data-filter-unique-pattern');
            var filterUniqueRegex = new RegExp(filterUniquePattern);
            var filterLabelPattern = this.getAttribute('data-filter-label-pattern');
            var filterLabelRegex = new RegExp(filterLabelPattern);

            $.ajax({
                type: 'GET',
                url: proxyUrl,
                success: function(json, textStatus, request) {
                    if (json[1] !== undefined && json[1].length > 0) {

                        // Build different array structure (prepare sort)
                        var references = [];
                        let countRegex = new RegExp(/\((\d+)\)$/);
                        for (let i=0; i<json[1].length; ++i) {
                            let label = json[1][i];
                            let groupLabel = label.replace(countRegex, '').trim();

                            let description = json[2][i];
                            let url = json[3][i];

                            let matchCount = label.match(countRegex);
                            let count = 1;
                            if (matchCount != null)
                                count = parseInt(matchCount[1]);

                            let sortPriority = 1;
                            if (label.match(sortBottomRegex))
                                sortPriority = 2;

                            if (filterLabelPattern == '' || !label.match(filterLabelRegex))
                                references.push({ label: label, groupLabel: groupLabel, description: description, url: url, count: count, sortPriority: sortPriority });
                        }

                        // sort by priority, then alphabetically
                        references.sort(function(a, b) {
                            if (a.sortPriority < b.sortPriority)
                                return -1;
                            if (a.sortPriority > b.sortPriority)
                                return 1;

                            return a.label.localeCompare(b.label);
                        });

                        // merge links with same label, if exact 1 url contains the correct gnd number
                        if (filterUniquePattern != '') {
                            let currentGroup = [];
                            let currentGroupStartIndex = 0;
                            var abortCondition = references.length;
                            for (let i=0; i<abortCondition;++i) {
                                let currentReference = references[i];
                                let nextReference = references[i+1];
                                currentGroup.push(currentReference);

                                // If we are at the end of the group
                                if (nextReference == undefined || nextReference.groupLabel != currentReference.groupLabel) {
                                    // Detect how many entries match the GND number
                                    var matchingIndexes = [];
                                    currentGroup.forEach(function (groupedReference, index) {
                                        if (groupedReference.url.match(filterUniqueRegex)) {
                                            matchingIndexes.push(index);
                                        }
                                    });

                                    // If we have exact 1 regex match & more than 1 entry, remove the invalid ones
                                    if (currentGroup.length > 1 && matchingIndexes.length == 1) {
                                        var matchingIndex = matchingIndexes[0];
                                        var removeOffset = 0;
                                        currentGroup.forEach(function (groupedReference, index) {
                                            if (index != matchingIndex) {
                                                let indexToRemove = index + currentGroupStartIndex - removeOffset;
                                                references.splice(indexToRemove, 1);
                                                --abortCondition;
                                                --i;
                                                ++removeOffset;
                                            }
                                        });
                                    }

                                    // Reset cached group
                                    currentGroup = [];
                                    currentGroupStartIndex = i+1;
                                }
                            }
                        }

                        // render HTML
                        let html = '<h2>' + headline + '</h2>';
                        html += '<ul class="list-group">';
                        var previousSortPriority = 1;
                        references.forEach(function(reference) {
                            if (reference.sortPriority != previousSortPriority) {
                                html += '</ul><ul class="list-group">';
                            }
                            previousSortPriority = reference.sortPriority;
                            html += '<li class="list-group-item tf-beacon-reference"><a class="tf-beacon-reference-link" href="' + reference.url + '" title="' + TueFind.EscapeHTML(reference.description) + '" target="_blank" property="sameAs">' + TueFind.EscapeHTML(reference.label) + '</a></li>';
                        });
                        html += '</ul>';
                        $(container).append(html);

                        // check if urls are valid (only if special URL parameter is set)
                        // Note that CORS needs to be disabled in your browser for this to work.
                        // See also:
                        // - https://github.com/ubtue/tuefind/issues/1924
                        // - https://medium.com/swlh/avoiding-cors-errors-on-localhost-in-2020-5a656ed8cefa
                        const urlParams = new URLSearchParams(window.location.search);
                        if (urlParams.get('checkUrls') == 'true') {
                            $('.tf-beacon-reference').each(function() {
                                $(this).css('backgroundColor', 'yellow');
                                var urlToCheck = $(this).children('.tf-beacon-reference-link').attr('href');
                                var targetBackground = $(this);
                                $.ajax({
                                    type: 'GET',
                                    url: urlToCheck,
                                    complete: function(jqXHR, textStatus) {
                                        let color = 'red';
                                        if (textStatus == 'success')
                                            color = 'green';
                                        targetBackground.css('backgroundColor', color);
                                    }
                                });
                            });
                        }
                    }
                }
            });
        });
    },

    GetImagesFromWikidata: function() {
        $('.tf-wikidata-image').each(function() {
            var placeholder = this;
            var imageUrl = this.getAttribute('data-url');
            var parentBlock = $(placeholder).parent();
            $.ajax({
                type: 'GET',
                url: imageUrl,
                success: function(image, textStatus, request) {
                    // example for embedding, see:
                    // https://commons.wikimedia.org/wiki/File:Angela_Merkel._Tallinn_Digital_Summit.jpg
                    // => "Use this file on the web"
                    let artist = TueFind.StripHtmlTags(request.getResponseHeader('artist'));
                    let license = request.getResponseHeader('link');
                    let title = '&copy; ' + TueFind.EscapeHTML(artist);
                    if (license != null) {
                        let pattern = /<([^>]+)>;\s*rel="license";\s*title="([^"]+)"/;
                        let match = pattern.exec(license);
                        title += ' <a href="' + match[1] + '" target="_blank">' + match[2] + '</a>';
                    }
                    title += ', via Wikimedia Commons';
                    let content = '<figure style="max-width: 200px;">';
                    content += '<img src="' + imageUrl + '" title="' + TueFind.StripHtmlTags(title) + '">';
                    content += '<figcaption style="text-align: center;">' + title + '</figcaption>';
                    content += '</figure>';
                    $(placeholder).append(content);
                },
                statusCode: {
                    200: function() {
                        parentBlock.removeClass('tf-d-none');
                        parentBlock.next().removeClass('col-md-auto');
                        parentBlock.next().removeClass('col-lg-auto');
                        parentBlock.next().addClass('col-md-9');
                        parentBlock.next().addClass('col-lg-9');
                    }
                }
            });
        });
    },

    GetJOPInformation: function(jop_place_holder_id, jop_icons_id, url_ajax_proxy, url_html, part_img,
                                 available_online_text, check_availability_text) {
        // service documentation, see http://www.zeitschriftendatenbank.de/fileadmin/user_upload/ZDB/pdf/services/JOP_Dokumentation_XML-Dienst.pdf
        $.ajax({
            type: "GET",
            url: url_ajax_proxy,
            dataType: "xml",
            success: function (xml) {
                $(document).ready(function () {
                    var replacement = "";
                    var filter = [];

                    $(xml).find('Result').each(function (index, value) {
                        let state = $(this).attr("state");
                        if (state >= 0 && state <= 3) {
                            $('#' + jop_icons_id).removeAttr("style");
                            let accessURL = $(value).find('AccessURL').first().text();
                            if (accessURL) {
                                if (filter[accessURL] != 1) {
                                    if (replacement)
                                        replacement += '<br/>';
                                    replacement += '<a href="' + accessURL + '"><i class="fa fa-external-link"></i> '
                                            + available_online_text + '.</a>';
                                    filter[accessURL] = 1;
                                }
                            } else {  // Hopefully available in print!
                                let location = $(value).find('Location').first().text();
                                let call_number = $(value).find('Signature').first().text();
                                let label = location;
                                if (call_number)
                                    label += " (" + call_number + ")";
                                if (filter[label] != 1) {
                                    if (replacement)
                                        replacement += '<br/>';
                                    replacement += label;
                                    filter[label] = 1;
                                }
                            }
                        } else if (state == 4 || state == 10) {
                            if (replacement == "") {
                                replacement = '<a href="' + url_html + '" target="_blank"><i class="fa fa-external-link"></i> ' +
                                              part_img + check_availability_text + '</a>';
                                // We get an 1x1 pixel gif from JOP that can be seen as an empty line
                                // => remove it
                                $("#" + jop_icons_id).remove();
                            }
                        }
                    });
                    if (replacement != "") {
                        $("#" + jop_place_holder_id).each(function () {
                            $(this).replaceWith(replacement);
                        });
                    } else {
                        $("#" + jop_place_holder_id).each(function () {
                            $(this).replaceWith('<?=$this->transEsc("Not available")?>.');
                        })
                    }
                });
            }, // end success
            error: function (xhr, ajaxOptions, thrownError) {
                $("#" + jop_place_holder_id).each(function () {
                    $(this).replaceWith('Invalid server response. (JOP server down?)');
                })
                if (window.console && window.console.log) {
                    console.log("Status: " + xhr.status + ", Error: " + thrownError);
                }
            }
        }); // end ajax
    },

    // helper function to set focus on a specified input field, also sets cursor position to end of field content
    SetFocus: function(input_selector) {
        // intentionally use .each so we do not need to test if empty
        $(input_selector).each(function() {
            // here we use native JS instead of jQuery to avoid problems
            this.focus();
            this.setSelectionRange(this.value.length, this.value.length);
        });
    },

    StripHtmlTags: function(html) {
        let temporalDivElement = document.createElement("div");
        temporalDivElement.innerHTML = html;
        return temporalDivElement.textContent || temporalDivElement.innerText || "";
    },

    HandlePassedFulltextQuery : function() {
        const url_query = window.location.search;
        const url_params = new URLSearchParams(url_query);
        const fulltextquery = url_params.get('fulltextquery');
        const fulltextscope = url_params.get('fulltextscope');
        if (!fulltextquery)
            return;
        $('html, body').animate({
            scrollTop: $('#itemFTSearchScope').offset().top
        }, 'fast');
        let searchForm_fulltext = $('#searchForm_fulltext');
        searchForm_fulltext.val(fulltextquery);
    },

    WildcardHandler : function(query_text) {
        const forbidden_chars = /[*?]/i;
        if (forbidden_chars.test(query_text)) {
            alert(VuFind.translate("fulltext_wildcard_error"));
            return false;
        }
        return true;
    },

    CheckWildcards : function(event) {
        // Case 1: ItemFulltextSearch
        if (event.type == 'submit' && event.target.id == 'ItemFulltextSearchForm')
            return this.WildcardHandler($("#searchForm_fulltext").val());
        // Case 2: The submit button was pressed
        // Case 3: The tab nav was chosen
        else if ((event.type == 'submit' && this.GetSearchboxSearchContext() == 'search2') ||
                 (event.type == 'click' && event.view.location.href.match('/Search2/')) ) {
                 return this.WildcardHandler($("#searchForm_lookfor").val());
        }
        return true;
    },

    ItemFullTextSearch: function(home_url, record_id, fulltext_types) {
        $(document).ready(function(){
            TueFind.HandlePassedFulltextQuery();
            $("#ItemFulltextSearchForm").submit(function(){
                TueFind.GetFulltextSnippets(home_url,
                                            record_id,
                                            $("#searchForm_fulltext").val(),
                                            true,
                                            $("#itemFTSearchScope").val(),
                                            $("#itemFTTextTypeScope").val() == "All Types" ? fulltext_types : $("#itemFTTextTypeScope").val())
                TueFind.CheckWildcards("ItemFulltextSearchForm");
                return false;
            });
            $("#ItemFulltextSearchForm").submit();
        });
    },

    ShowMoreButtonFavoriteList: function() {
      let maxElements = 3;
      let countListItems = 0;
      let showMoreButton = false;
      $('.savedLists.loaded').each(function() {
	if (!$(this).hasClass('tf-loaded-custom')) {
	  $(this).find('li').each(function() {
	    countListItems++;
	    if (countListItems > maxElements) {
	      $(this).hide();
	      showMoreButton = true;
	    }
	  });
	  if (showMoreButton === true) {
	    $('<span class="tf-favoritesListMoreButton">' + VuFind.translate('more') + '</span>').insertAfter($(this).find('ul'));
	  }
	  $(this).removeClass('tf-d-none');
	  $('.tf-favoritesListMoreButton').click(function() {
	    $('.tf-favoritesListModal').click();
	  });
	  $(this).addClass('tf-loaded-custom');
	  console.log('tf-loaded-custom');
	}
      });
    },

    SwitchRSSFeedData: function(element) {
        let actionType = 'add';
        if(!element.is(':checked')) {
            actionType = 'remove';
        }
        if(element.val() == 'unsubscribe_email'){
            actionType = 'subscribe_email';
            element.val('subscribe_email');
            $('.rssEmailTimestampBlock').removeClass('tf-d-none');
            let today = new Date();
            let curDate = today.getFullYear()+'-'+(today.getMonth()+1)+'-'+today.getDate();
            let curTime = today.toLocaleTimeString();
            let curTimestamp = curDate+" "+curTime;
            $('.rssEmailTimestampBlock span').text(curTimestamp);
        }else if(element.val() == 'subscribe_email'){
            actionType = 'unsubscribe_email';
            element.val('unsubscribe_email');
            $('.rssEmailTimestampBlock').addClass('tf-d-none');
        }
        let rssID = element.data('id');
        $.ajax({
            type: "POST",
            url: "/MyResearch/RssFeedSettings",
            data: {action:actionType,id:rssID},
            dataType: "json"
        });
    }
};


$(document).ready(function () {

    // Home search: set focus on first input field of first search group
    if (window.location.pathname.match(/\/Search(2)?\/Home$/i) || window.location.pathname === '/') {
        TueFind.SetFocus('#searchForm_lookfor');
    // advanced search: set focus on first input field of first search group
    } else if (window.location.pathname.match(/\/Search\/Advanced$/i)) {
        TueFind.SetFocus('#search_lookfor0_0');
    // keywordchainsearch: set focus on 2nd input field
    } else if (window.location.pathname.match(/\/Keywordchainsearch/i)) {
        TueFind.SetFocus('#kwc_input');
    // alphabrowse: set focus on "starting from" edit field
    } else if (window.location.pathname.match(/\/Alphabrowse/i)) {
        TueFind.SetFocus('#alphaBrowseForm_from');
    }

    $('.tuefind-event-resetsearchhandlers').click(function(){
        TueFind.ResetSearchHandlers();
        return TueFind.CheckWildcards(event);
    });

    $('.tuefind-event-searchForm-on-submit').submit(function(){
        return TueFind.CheckWildcards(event);
    });

    TueFind.AddContentAnchors();
    TueFind.AdjustSearchHandlers();
    setInterval(TueFind.ShowMoreButtonFavoriteList, 1000);

    $('.rssLabel').change(function(){
        TueFind.SwitchRSSFeedData($(this));
    })

});
