<?php

namespace TueFind\RecordDriver;

class SolrAuth extends \VuFind\RecordDriver\SolrAuth {

    /**
     * Get GND Number from 035a (DE-588) or null
     * @return string
     */
    public function getGNDNumber() {
        $pattern = '"^\(DE-588\)"';
        $values = $this->getFieldArray('035', 'a');
        foreach ($values as $value) {
            if (preg_match($pattern, $value))
                return preg_replace($pattern, '', $value);
        }
    }
}
