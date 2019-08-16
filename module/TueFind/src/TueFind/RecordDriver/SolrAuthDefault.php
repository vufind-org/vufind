<?php

namespace TueFind\RecordDriver;

class SolrAuthDefault extends \VuFind\RecordDriver\SolrAuthMarc {
    public function getORCID() {
        return $this->fields['orcid'] ?? null;
    }

    public function getVIAF() {
        return $this->fields['viaf'] ?? null;
    }
}
