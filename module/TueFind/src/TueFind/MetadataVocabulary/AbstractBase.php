<?php

namespace TueFind\MetadataVocabulary;

abstract class AbstractBase implements MetadataVocabularyInterface {
    protected $vocabFieldToGenericFieldsMap = [];
    protected $metaHelper;

    public function __construct(\Zend\View\Helper\HeadMeta $metaHelper) {
        $this->metaHelper = $metaHelper;
    }

    public function addMetatags(\VuFind\RecordDriver\DefaultRecord $driver) {
        $genericFieldsToValuesMap = ['author' => array_merge($driver->getPrimaryAuthors(),
                                                     $driver->getSecondaryAuthors(),
                                                     $driver->getCorporateAuthors()),
                                     'container_title' => $driver->getContainerTitle(),
                                     'date' => $driver->getPublicationDates(),
                                     'doi' => $driver->getCleanDOI(),
                                     'endpage' => $driver->getContainerEndPage(),
                                     'isbn' => $driver->getCleanISBN(),
                                     'issn' => $driver->getCleanISSN(),
                                     'issue' => $driver->getContainerIssue(),
                                     'language' => $driver->getLanguages(),
                                     'publisher' => $driver->getPublishers(),
                                     'startpage' => $driver->getContainerStartPage(),
                                     'title' => $driver->getTitle(),
                                     'volume' => $driver->getContainerVolume(),
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
