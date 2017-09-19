<?php

namespace TuFind\RecordDriver;
use VuFind\Exception\LoginRequired as LoginRequiredException;

class SolrMarc extends \TuFind\RecordDriver\SolrDefault
{
    /**
     * Wrapper for parent's getFieldArray, allowing multiple fields to be
     * processed at once
     *
     * @param array $fields_and_subfields array(0 => field as string, 1 => subfields as array or string (string only 1))
     * @param bool $concat
     * @param string $separator
     *
     * @return array
     */
    protected function getFieldsArray($fields_and_subfields, $concat=true, $separator=' ') {
        $fields_array = array();
        foreach ($fields_and_subfields as $field_and_subfield) {
            $field = $field_and_subfield[0];
            $subfields = (isset($field_and_subfield[1])) ? $field_and_subfield[1] : null;
            if (!is_null($subfields) && !is_array($subfields)) $subfields = array($subfields);
            $field_array = $this->getFieldArray($field, $subfields, $concat, $separator);
            $fields_array = array_merge($fields_array, $field_array);
        }
        return array_unique($fields_array);
    }

    public function getSuperiorRecord() {
        $_773_field = $this->getMarcRecord()->getField("773");
        if (!$_773_field)
            return NULL;
        $subfields = $this->getSubfieldArray($_773_field, ['w'], /* $concat = */false);
        if (!$subfields)
            return NULL;
        $ppn = substr($subfields[0], 8);
        if (!$ppn || strlen($ppn) != 9)
            return NULL;
        return $this->getRecordDriverByPPN($ppn);
    }

    public function isAvailableInTubingenUniversityLibrary() {
        $ita_fields = $this->getMarcRecord()->getFields("ITA");
        return (count($ita_fields) > 0);
    }

    public function isDependentWork() {
        $leader = $this->getMarcRecord()->getLeader();
        // leader[7] is set to 'a' if we have a dependent work
        return ($leader[7] == 'a') ? true : false;
    }

    public function isPrintedWork() {
        $fields = $this->getMarcRecord()->getFields("007");
        foreach ($fields as $field) {
            if ($field->getData()[0] == 't')
                return true;
        }
        return false;
    }

    public function workIsTADCandidate() {
        return $this->isDependentWork() && $this->isPrintedWork() && $this->isAvailableInTubingenUniversityLibrary();
    }
}
