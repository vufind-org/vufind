<?php

namespace TueFind\MetadataVocabulary;

class HighwirePress extends AbstractBase {

    // https://jira.duraspace.org/secure/attachment/13020/Invisible_institutional.pdf
    protected $vocabFieldToGenericFieldsMap = ['citation_author' => 'author',
                                               'citation_date' => 'date',
                                               'citation_doi' => 'doi',
                                               'citation_firstpage' => 'startpage',
                                               'citation_isbn' => 'isbn',
                                               'citation_issn' => 'issn',
                                               'citation_issue' => 'issue',
                                               'citation_journal_title' => 'container_title',
                                               'citation_language' => 'language',
                                               'citation_lastpage' => 'endpage',
                                               'citation_publisher' => 'publisher',
                                               'citation_title' => 'title',
                                               'citation_volume' => 'volume',
                                            ];
}
