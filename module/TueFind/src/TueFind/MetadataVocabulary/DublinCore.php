<?php

namespace TueFind\MetadataVocabulary;

class DublinCore extends AbstractBase {
    protected $vocabFieldToGenericFieldsMap = ['DC.citation.epage' => 'endpage',
                                               'DC.citation.issue' => 'issue',
                                               'DC.citation.spage' => 'startpage',
                                               'DC.citation.volume' => 'volume',
                                               'DC.creator' => 'author',
                                               'DC.identifier' => ['doi', 'isbn', 'issn'],
                                               'DC.issued' => 'date',
                                               'DC.language' => 'language',
                                               'DC.publisher' => 'publisher',
                                               'DC.relation.ispartof' => 'container_title',
                                               'DC.title' => 'title',
                                            ];
}
