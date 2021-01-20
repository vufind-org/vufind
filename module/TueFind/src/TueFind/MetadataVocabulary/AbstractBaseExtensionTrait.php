<?php

namespace TueFind\MetadataVocabulary;

/**
 * This trait is needed because multiple inheritance is not possible in PHP.
 * We need the used vocabularies like e.g. HighwirePress to use changed data,
 * which can be achieved by each single vocabulary using this trait.
 */
trait AbstractBaseExtensionTrait {
    protected function getGenericData(\VuFind\RecordDriver\AbstractBase $driver) {
        $genericData = parent::getGenericData($driver);
        $genericData['author'] = $driver->getAuthorNames();
        return $genericData;
    }
}
