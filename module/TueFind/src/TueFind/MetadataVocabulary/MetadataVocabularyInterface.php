<?php

namespace TueFind\MetadataVocabulary;

interface MetadataVocabularyInterface {
    public function addMetatags(\VuFind\RecordDriver\DefaultRecord $driver);
}
