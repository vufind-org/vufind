<?php

namespace KrimDok\RecordDriver;

class SolrMarc extends SolrDefault
{
    const SUBITO_BROKER_ID = 'KRIMDOK';

    /**
     * Get all subject headings associated with this record.  Each heading is
     * returned as an array of chunks, increasing from least specific to most
     * specific.
     *
     * @param bool $extended Whether to return a keyed array with the following
     * keys:
     * - heading: the actual subject heading chunks
     * - type: heading type
     * - source: source vocabulary
     *
     * @return array
     */
    public function getAllSubjectHeadings($extended = false)
    {
        // Get default headings from parent
        $defaultHeadings = parent::getAllSubjectHeadings($extended);

        // Add custom headings
        $customHeadings = [];
        $fields = $this->getMarcReader()->getFields('689');
        $current = [];
        $currentID = 0;
        foreach ($fields as $field) {
            $id = $field['i1'];
            if ($id != $currentID && !empty($current)) {
                $customHeadings[] = $current;
                $current = [];
            }
            foreach ($field['subfields'] as $subfield) {
                if (!is_numeric($subfield['code']) && strlen($subfield['data']) > 2) {
                    if (!$extended) {
                        $current[] = $subfield['data'];
                    } else {
                        $current[] = [
                            'heading' => $subfield['data'],
                            'type' => 'subject',
                            'source' => '',
                        ];
                    }
                }
            }
            $currentID = $id;
        }
        if (!empty($current)) {
            $customHeadings[] = $current;
        }

        $fields = $this->getMarcReader()->getFields('LOK');
        foreach ($fields as $field) {
            $current = [];
            $subfields = $field['subfields'];
            $firstSubfieldData = $subfields[0]['data'] ?? null;
            if ($firstSubfieldData === '689  ') {
                foreach ($subfields as $subfield) {
                    if ($subfield['code'] === 'a' && strlen($subfield['data']) > 1) {
                        if (!$extended) {
                            $current[] = $subfield['data'];
                        } else {
                            $current[] = [
                                'heading' => $subfield['data'],
                                'type' => 'subject',
                                'source' => '',
                            ];
                        }
                    }
                }
            }
            if (!empty($current)) {
                $customHeadings[] = $current;
            }
        }

        // merge, unique, sort
        $headings = array_merge($defaultHeadings, $customHeadings);
        $headings = array_unique($headings, SORT_REGULAR);
        uasort($headings, function($a, $b) {
            $aSortKey = implode('#', $a);
            $bSortKey = implode('#', $b);
            return strnatcmp($aSortKey, $bSortKey);
        });

        return $headings;
    }
}
