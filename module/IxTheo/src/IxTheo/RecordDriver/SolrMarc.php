<?php

namespace IxTheo\RecordDriver;

class SolrMarc extends SolrDefault
{
    const SUBITO_BROKER_ID = 'IXTHEO';

    public function canUseTAD($userId)
    {
        $formats_tad_allowed = array('Article');
        $user_allowed = $this->getDbTable('user')->canUseTAD($userId);
        if(!$user_allowed) {
            return false;
        }

        $formats = $this->getFormats();
        $intersection = array_intersect($formats_tad_allowed, $this->getFormats());
        $tad_formats_allowed = !empty($intersection);

        return $tad_formats_allowed;
    }

    /**
     * @param array $properties  associative array with name => value
     * @return int
     */
    public function getChildRecordCountWithProperties($properties)
    {
        // Shortcut: if this record is not the top record, let's not find out the
        // count. This assumes that contained records cannot contain more records.
        if (!$this->containerLinking
            || empty($this->fields['is_hierarchy_id'])
            || null === $this->searchService
        ) {
            return 0;
        }

        $safeId         = addcslashes($this->fields['is_hierarchy_id'], '"');

        $query_string   = 'hierarchy_parent_id:"' . $safeId . '"';
        foreach($properties as $key => $value) {
            $query_string .= ' AND ' . $key . ':"' . addcslashes($value, '"') . '"';
        }

        $query = new \VuFindSearch\Query\Query(
            $query_string
        );
        return $this->searchService->search('Solr', $query, 0, 0)->getTotal();
    }

    private function getSubfieldPairs($field_tag, $first_subfield_code, $second_subfield_code)
    {
        $matches = [];
        $first_subfield_contents = null;
        foreach ($this->getMarcReader()->getFields($field_tag) as $field) {
            foreach ($this->getMarcReader()->getSubfields($field) as $subfield) {
                $subfield_code = $subfield;
                if ($subfield_code == $first_subfield_code) {
                    if ($first_subfield_contents != null)
                        $matches[] = $first_subfield_contents . '|';
                    $first_subfield_contents = $subfield;
                } elseif ($subfield_code == $second_subfield_code) {
                    if ($first_subfield_contents != null) {
                        $matches[] = $first_subfield_contents . '|' . $subfield;
                        $first_subfield_contents = null;
                    }
                }
            }
            if ($first_subfield_contents != null)
                $matches[] = $first_subfield_contents . '|';
        }

        return $matches;
    }

    public function getEnclosedTitlesWithAuthors()
    {
        return array_merge($this->getSubfieldPairs('249', 'a', 'v'), $this->getSubfieldPairs('505', 't','r'));
    }

    public function getKeyWordChains()
    {
        if (isset($this->fields['key_word_chains'])) {
            $keywordchains = $this->fields['key_word_chains'];
            // Currently topic fields are also copied
            // These contain GND numbers and other stuff that should
            // not be displayed in keywordchains
            // As to topics VuFind directly evaluates the full records
            // and can thus directly filter out number subfields
            // We lose this information when evaluating the SOLR field
            // and thus have to filter manually
            $keywordchains = preg_replace('/\sgnd\s/', '', $keywordchains);
            $keywordchains = preg_replace('/\(\w{2}-\d{3}\)[\dX-]+\s*/', '', $keywordchains);
            return $keywordchains;
        }
        else {
          return '';
        }
    }

    /**
     * Returns persistent identifiers as array
     * e.g. array('DOI' => array(<doi1>, <doi2>),
     *            'URN' => array(<urn1>, <urn2>),);
     *
     * keys like 'DOI' will only exist if at last 1 DOI is available
     *
     * @return array
     */
    public function getTypesAndPersistentIdentifiers()
    {
        $result  = array();
        $rawdata = isset($this->fields['types_and_persistent_identifiers']) ? $this->fields['types_and_persistent_identifiers'] : array();

        foreach ($rawdata as $entry) {
            $entry_splitted = explode(':', $entry, 2);
            $result_type    = $entry_splitted[0];
            $result_value   = $entry_splitted[1];

            if (!isset($result[$result_type]))
                $result[$result_type] = array();
            $result[$result_type][] = $result_value;
        }

        return $result;
    }

    public function getTimeRangesString()
    {
        // At the moment, only one time range is allowed, so we simply return
        // the first found subfield.
        return $this->getFirstFieldValue('TIM', ['b']);
    }

}
