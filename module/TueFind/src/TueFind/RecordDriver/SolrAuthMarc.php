<?php

namespace TueFind\RecordDriver;

class SolrAuthMarc extends SolrAuthDefault {

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

    protected function getLifeDates() {
        $lifeDates = ['birth' => null, 'death' => null];

        $fields = $this->getMarcRecord()->getFields('548');
        foreach ($fields as $field) {
            $typeSubfield = $field->getSubfield('4');
            if ($typeSubfield !== false && $typeSubfield->getData() == 'datx') {
                if (preg_match('"^(\d{1,2}\.\d{1,2}\.\d{1,4})-(\d{1,2}\.\d{1,2}\.\d{1,4})$"', $field->getSubfield('a')->getData(), $hits)) {
                    $lifeDates['birth'] = $hits[1];
                    $lifeDates['death'] = $hits[2];
                    break;
                }
            }
        }

        return $lifeDates;
    }

    protected function getLifePlaces() {
        $lifePlaces = ['birth' => null, 'death' => null];

        $fields = $this->getMarcRecord()->getFields('551');
        foreach ($fields as $field) {
            $typeSubfield = $field->getSubfield('4');
            if ($typeSubfield !== false) {
                switch($typeSubfield->getData()) {
                case 'ortg':
                    $lifePlaces['birth'] = $field->getSubfield('a')->getData() ?? null;
                    break;
                case 'orts':
                    $lifePlaces['death'] = $field->getSubfield('a')->getData() ?? null;
                    break;
                }

            }
        }
        return $lifePlaces;
    }

    /**
     * Get birth date or year if date is not set
     * @return string
     */
    public function getBirthDateOrYear() {
        return $this->getBirthDate() ?? $this->getBirthYear();
    }

    /**
     * Get exact birth date
     * @return string
     */
    public function getBirthDate() {
        $lifeDates = $this->getLifeDates();
        return $lifeDates['birth'] ?? null;
    }

    /**
     * Get birth place
     * @return string
     */
    public function getBirthPlace() {
        return $this->getLifePlaces()['birth'] ?? null;
    }

    /**
     * Get birth year
     * @return string
     */
    public function getBirthYear() {
        $pattern = '"^(\d+)(-?)(\d+)?$"';
        $values = $this->getFieldArray('100', ['d']);
        foreach ($values as $value) {
            if (preg_match($pattern, $value, $hits))
                return $hits[1];
        }
    }

    /**
     * Get death date or year if date is not set
     * @return string
     */
    public function getDeathDateOrYear() {
        return $this->getDeathDate() ?? $this->getDeathYear();
    }

    /**
     * Get exact death date
     * @return string
     */
    public function getDeathDate() {
        $lifeDates = $this->getLifeDates();
        return $lifeDates['death'] ?? null;
    }

    /**
     * Get death place
     * @return string
     */
    public function getDeathPlace() {
        return $this->getLifePlaces()['death'] ?? null;
    }

    /**
     * Get death year
     * @return string
     */
    public function getDeathYear() {
        $pattern = '"^(\d+)(-?)(\d+)?$"';
        $values = $this->getFieldArray('100', ['d']);
        foreach ($values as $value) {
            if (preg_match($pattern, $value, $hits) && isset($hits[3]))
                return $hits[3];
        }
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
     * Get multiple notations of the name
     * (e.g. for external searches like wikidata)
     * (e.g. "King, Martin Luther" => "Martin Luther King")
     */
    public function getNameAliases() {
        $names = [];
        $name = $this->getName();
        $names[] = $name;
        $alias = preg_replace('"^([^,]+)\s*,\s*([^,]+)$"', '\\2 \\1', $name);
        if ($alias != $name)
            $names[] = $alias;
        return $names;
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
