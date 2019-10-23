<?php

namespace TueFind\MetadataVocabulary;

class PRISM extends AbstractBase {
    // https://www.idealliance.org/prism-metadata
    protected $vocabFieldToGenericFieldsMap = ['prism.doi' => 'doi',
                                               'prism.endingPage' => 'endpage',
                                               'prism.isbn' => 'isbn',
                                               'prism.issn' => 'issn',
                                               'prism.startingPage' => 'startpage',
                                               'prism.title' => 'title',
                                               'prism.volume' => 'volume',
                                            ];
}
