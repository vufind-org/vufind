<?php

namespace TueFind\RecordDriver;

class SolrAuthDefault extends \VuFind\RecordDriver\SolrAuthMarc {
    public function getISNI() {
        return $this->fields['isni'] ?? null;
    }

    /**
     * Overwrite VuFind's standard lccn mechanism.
     * => Take return value directly from Solr.
     * => Also, VuFind defines lccn as a "multiple" field.
     *    We only return the first value instead of an array.
     */
    public function getLCCN() {
        return $this->fields['lccn'][0] ?? null;
    }

    public function getORCID() {
        return $this->fields['orcid'] ?? null;
    }

    public function getProfessions() {
        return $this->fields['profession'] ?? [];
    }

    public function getVIAF() {
        return $this->fields['viaf'] ?? null;
    }

    public function getWikidataId() {
        return $this->fields['wikidata'] ?? null;
    }
}
