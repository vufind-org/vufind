<?php

namespace TueFind\MetadataVocabulary;

class BEPress extends AbstractBase {
    // http://div.div1.com.au/div-thoughts/div-commentaries/66-div-commentary-metadata
    protected $vocabFieldToGenericFieldsMap = ['bepress_citation_author' => 'author',
                                               'bepress_citation_date' => 'date',
                                               'bepress_citation_doi' => 'doi',
                                               'bepress_citation_firstpage' => 'startpage',
                                               'bepress_citation_isbn' => 'isbn',
                                               'bepress_citation_issn' => 'issn',
                                               'bepress_citation_issue' => 'issue',
                                               'bepress_citation_journal_title' => 'container_title',
                                               'bepress_citation_lastpage' => 'endpage',
                                               'bepress_citation_publisher' => 'publisher',
                                               'bepress_citation_title' => 'title',
                                               'bepress_citation_volume' => 'volume',
                                            ];
}
