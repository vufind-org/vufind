<?php

namespace TueFind\Meta;

abstract class AbstractBase implements MetaInterface {
    protected $map = [];
    protected $metaHelper;

    public function __construct(\Zend\View\Helper\HeadMeta $metaHelper) {
        $this->metaHelper = $metaHelper;
    }

    public function addMetatags(\VuFind\RecordDriver\DefaultRecord $driver) {
        $fieldToMethodMap = ['title' => $driver->getTitle(),
                             'doi' => $driver->getCleanDOI(),
                             'isbn' => $driver->getCleanISBN(),
                             'issn' => $driver->getCleanISSN(),
                             ];

        foreach ($fieldToMethodMap as $genericField => $value) {
            if ($value != '') {
                $targetField = $this->map[$genericField] ?? null;
                if ($targetField !== null) {
                    $this->metaHelper->appendName($targetField, $value);
                }
            }
        }
    }
}
