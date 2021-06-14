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

    public function getOccupations() {
        return $this->fields['occupation'] ?? [];
    }

    public function getVIAFs(): array {
        $viafs = $this->fields['viaf'] ?? [];
        if (!is_array($viafs))
            $viafs = [$viafs];
        return $viafs;
    }

    public function getWikidataId() {
        return $this->fields['wikidata'] ?? null;
    }

    public function getType() {
        return $this->fields['type'] ?? null;
    }
}
