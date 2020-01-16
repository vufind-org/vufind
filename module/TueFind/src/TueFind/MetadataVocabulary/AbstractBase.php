<?php

namespace TueFind\MetadataVocabulary;

abstract class AbstractBase implements MetadataVocabularyInterface {
    protected $vocabFieldToGenericFieldsMap = [];
    protected $metaHelper;

    public function __construct(\Zend\View\Helper\HeadMeta $metaHelper) {
        $this->metaHelper = $metaHelper;
    }

    public function addMetatags(\VuFind\RecordDriver\AbstractBase $driver) {
        $genericFieldsToValuesMap = ['author' => array_merge(
                                        $driver->tryMethod('getPrimaryAuthors') ?? [],
                                        $driver->tryMethod('getSecondaryAuthors') ?? [],
                                        $driver->tryMethod('getCorporateAuthors') ?? []
                                     ),
                                     'container_title' => $driver->tryMethod('getContainerTitle'),
                                     'date' => $driver->tryMethod('getPublicationDates'),
                                     'doi' => $driver->tryMethod('getCleanDOI'),
                                     'endpage' => $driver->tryMethod('getContainerEndPage'),
                                     'isbn' => $driver->tryMethod('getCleanISBN'),
                                     'issn' => $driver->tryMethod('getCleanISSN'),
                                     'issue' => $driver->tryMethod('getContainerIssue'),
                                     'language' => $driver->tryMethod('getLanguages'),
                                     'publisher' => $driver->tryMethod('getPublishers'),
                                     'startpage' => $driver->tryMethod('getContainerStartPage'),
                                     'title' => $driver->tryMethod('getTitle'),
                                     'volume' => $driver->tryMethod('getContainerVolume'),
                                    ];

        foreach ($this->vocabFieldToGenericFieldsMap as $vocabField => $genericFields) {
            if (!is_array($genericFields))
                $genericFields = [$genericFields];
            foreach ($genericFields as $genericField) {
                $values = $genericFieldsToValuesMap[$genericField] ?? [];
                if ($values) {
                    if (!is_array($values))
                        $values = [$values];
                    foreach ($values as $value)
                        $this->metaHelper->appendName($vocabField, $value);
                }
            }
        }
    }
}
