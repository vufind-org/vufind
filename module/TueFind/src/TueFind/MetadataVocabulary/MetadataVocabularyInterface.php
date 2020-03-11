<?php

namespace TueFind\MetadataVocabulary;

interface MetadataVocabularyInterface {
    /**
     * Add metatags to the current HTML page. Use RecordDriver as datasource.
     *
     * Note that @demiankatz insisted on using AbstractBase instead of DefaultRecord here
     * for higher flexibility. That's why all implementations need to use "tryMethod"
     * instead of calling the methods directly.
     *
     * @param \TueFind\RecordDriver\SolrMarc $driver
     */
    public function addMetatags(\TueFind\RecordDriver\SolrMarc $driver);
}
