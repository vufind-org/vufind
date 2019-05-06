<?php

namespace TueFind\RecordDriver;

class SolrAuthMarc extends \VuFind\RecordDriver\SolrAuthMarc {

    /**
     * Get List of all beacon references.
     * @return [['title', 'url']]
     */
    public function getBeaconReferences() {
        $beacon_references = [];
        $beacon_fields = $this->getMarcRecord()->getFields('BEA');
        if (is_array($beacon_fields)) {
            foreach($beacon_fields as $beacon_field) {
                $name_subfield  = $beacon_field->getSubfield('a');
                $url_subfield   = $beacon_field->getSubfield('u');

                if ($name_subfield !== false && $url_subfield !== false)
                    $beacon_references[] = ['title' => $name_subfield->getData(),
                                            'url' => $url_subfield->getData()];
            }
        }
        return $beacon_references;
    }

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

    /**
     * Get locations from 551
     * @return [['name', 'type']]
     */
    public function getLocations() {
        $locations = [];
        $fields = $this->getMarcRecord()->getFields('551');
        foreach ($fields as $field) {
            $locations[] = ['name' => $field->getSubfield('a')->getData(),
                            'type' => $field->getSubfield('i')->getData()];
        }
        return $locations;
    }

    /**
     * Get Name from 100a
     * @return string
     */
    public function getName() {
        return $this->getFirstFieldValue('100', 'a');
    }

    /**
     * Get professions from 550
     * @return [['title']] (array to be extended)
     */
    public function getProfessions() {
        $professions = [];
        $fields = $this->getMarcRecord()->getFields('550');
        foreach ($fields as $field) {
            $title_subfield = $field->getSubfield('a');
            $type_subfield = $field->getSubfield('i');
            if ($title_subfield !== false && $type_subfield !== false && preg_match('"Beruf"i', $type_subfield->getData())) {
                $profession = ['title' => $title_subfield->getData()];
                $professions[] = $profession;
            }
        }
        return $professions;
    }
}
