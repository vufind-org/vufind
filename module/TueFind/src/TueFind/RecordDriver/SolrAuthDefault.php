<?php

namespace TueFind\RecordDriver;

class SolrAuthDefault extends \VuFind\RecordDriver\SolrAuthMarc {

    public function getGNDNumber()
    {
        return $this->fields['gnd'] ?? null;
    }

    public function getHeadingShort()
    {
        return $this->fields['heading_short'] ?? null;
    }

    public function getHeadingTimespan()
    {
        return $this->fields['heading_timespan'] ?? null;
    }

    public function getISNIs(): array {
        $isnis = $this->fields['isni'] ?? [];
        if (!is_array($isnis))
            $isnis = [$isnis];
        return $isnis;
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

    public function getORCIDs(): array {
        $orcids = $this->fields['orcid'] ?? [];
        if (!is_array($orcids))
            $orcids = [$orcids];
        return $orcids;
    }

    public function getOccupations($language='en') {
        return $this->fields['occupation_' . $language] ?? [];
    }

    public function getSubsystems(): array {
        return $this->fields['subsystem'] ?? [];
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
