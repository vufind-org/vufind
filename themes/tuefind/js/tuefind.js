var TueFind = {
    // helper function to add content anchors to content page links
    AddContentAnchors: function() {
        $('a').each(function() {
            let href = $(this).attr('href');
            if (href != undefined && !href.includes('#')) {
                if (href.match(/\/Content\//) || href.match(/\?subpage=/)) {
                    this.setAttribute('href', href + '#content');
                }
            }
        });
    },

    AdjustSearchHandlers: function() {
        // Make sure that a selection of the search handler is transparently adjusted for a potential reload
        // i.e. when changing the sort order
        var saved_search_handler = sessionStorage.getItem("tuefind_saved_search_handler");
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
            sessionStorage.setItem("tuefind_saved_search_handler", current_search_handler);
            $("#searchForm_type [value=" + previous_search_handler + "]").removeAttr('selected');
            $("#searchForm_type [value=" + current_search_handler + "]").attr('selected', 'selected');
            $('[name=type]:not([data-type-protected="1"])').val(current_search_handler);
        });
    },

    EscapeHTML: function(text) {
        return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    },

    GetFulltextSnippets: function(url, doc_id, query, verbose = false) {
        // Try to determine status
        $.ajax({
            type: "GET",
            url: url + "fulltextsnippetproxy/load?search_query=" + query + "&doc_id=" + doc_id + (verbose ? "&verbose=1" : ""),
            dataType: "json",
            success: function (json) {
                $(document).ready(function () {
                    var snippets = json['snippets'];
                    $("#snippet_place_holder_" + doc_id).each(function () {
                        if(snippets)
                          $(this).replaceWith('<div id="snippets_' + doc_id + '">' + snippets.join('<br/>') + '<br/>' +'</div>');
                    });
                    $("#snippets_" + doc_id).each(function () {
                        if (snippets) {
                            $(this).removeAttr('style');
                            var styles = snippets.map(a => a.style).join();
                            $(styles).appendTo("head");
                            var snippets_and_pages = snippets.map(a => a.snippet + '</br>[' + a.page + ']');
                            $(this).html(snippets_and_pages.join('<hr/>') + '<hr/>');
                        } else
                            $(this).html("");
                    });
                });
            }, // end success
            error: function (xhr, ajaxOptions, thrownError) {
                $("#snippet_place_holder").each(function () {
                    $(this).replaceWith('Invalid server response!!!!!');
                })
            }
        });
    },

    GetImagesFromWikidata: function() {
        $('.tf-wikidata-image').each(function() {
            var placeholder = this;
            var imageUrl = this.getAttribute('data-url');
            $.ajax({
                type: 'GET',
                url: imageUrl,
                success: function(image, textStatus, request) {
                    // example for embedding, see:
                    // https://commons.wikimedia.org/wiki/File:Angela_Merkel._Tallinn_Digital_Summit.jpg
                    // => "Use this file on the web"
                    let artist = request.getResponseHeader('artist');
                    let license = request.getResponseHeader('link');
                    let title = '&copy; ' + TueFind.EscapeHTML(artist);
                    if (license != null) {
                        let pattern = /<([^>]+)>;\s*rel="license";\s*title="([^"]+)"/;
                        let match = pattern.exec(license);
                        title += '[' + match[2] + ' by ' + match[1]+ ']';
                    }
                    title += ', via Wikimedia Commons';
                    let content = '<figure style="max-width: 200px;">';
                    content += '<img src="' + imageUrl + '" title="' + title + '">';
                    content += '<figcaption style="text-align: center;">' + title + '</figcaption>';
                    content += '</figure>';
                    $(placeholder).append(content);
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
        $(input_selector).focus();
        if ($(input_selector).length) {
           // now we are sure that element exists
           // don't assign input_field earlier, JS might crash if element doesnt exist
           let input_field = $(input_selector);
           input_field[0].setSelectionRange(input_field.val().length, input_field.val().length);
        }
    }
};


$(document).ready(function () {
    // advanced search: set focus on first input field of first search group
    if (window.location.href.match(/\/Search\/Advanced/i)) {
        TueFind.SetFocus('#search_lookfor0_0');
    // keywordchainsearch: set focus on 2nd input field
    } else if (window.location.href.match(/\/Keywordchainsearch\//i)) {
        TueFind.SetFocus('#kwc_input');
    // alphabrowse: set focus on "starting from" edit field
    } else if (window.location.href.match(/\/Alphabrowse\//i)) {
        TueFind.SetFocus('#alphaBrowseForm_from');
    }
    TueFind.AddContentAnchors();
    TueFind.AdjustSearchHandlers();
});
