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
        $results = $this->getMarcRecord()->getFields('689');
        $current = [];
        $currentID = 0;
        foreach ($results as $result) {
            $id = $result->getIndicator(1);
            if ($id != $currentID && !empty($current)) {
                $customHeadings[] = $current;
                $current = [];
            }
            $subfields = $result->getSubfields();
            if ($subfields) {
                foreach ($subfields as $subfield) {
                    if (!is_numeric($subfield->getCode()) && strlen($subfield->getData()) > 2) {
                        if (!$extended) {
                            $current[] = $subfield->getData();
                        } else {
                            $current[] = [
                                'heading' => $subfield->getData(),
                                'type' => 'subject',
                                'source' => '',
                            ];
                        }
                    }
                }
            }
            $currentID = $id;
        }
        if (!empty($current)) {
            $customHeadings[] = $current;
        }

        $results = $this->getMarcRecord()->getFields('LOK');
        foreach ($results as $result) {
            $current = [];
            $subfields = $result->getSubfields();
            if ($subfields && $subfields->bottom()->getData() === '689  ') {
                foreach ($subfields as $subfield) {
                    if ($subfield->getCode() === 'a' && strlen($subfield->getData()) > 1) {
                        if (!$extended) {
                            $current[] = $subfield->getData();
                        } else {
                            $current[] = [
                                'heading' => $subfield->getData(),
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

        // merge (+unique), sort & return headings
        $headings = array_merge($defaultHeadings, $customHeadings);
        sort($headings);
        $headings = array_unique($headings, SORT_REGULAR);
        return $headings;
    }
}
