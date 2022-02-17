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
        // THIS PART OF THE CODE IS THE SAME AS VUFIND4
        // This is all the collected data:
        $retval = [];

        // Try each MARC field one at a time:
        foreach ($this->subjectFields as $field => $fieldType) {
            // Do we have any results for the current field?  If not, try the next.
            $results = $this->getMarcReader()->getFields($field);
            if (!$results) {
                continue;
            }

            // If we got here, we found results -- let's loop through them.
            foreach ($results as $result) {
                // Start an array for holding the chunks of the current heading:
                $current = [];

                // Get all the chunks and collect them together:
                $subfields = $this->getMarcReader()->getSubfields($result);
                if ($subfields) {
                    foreach ($subfields as $subfield) {
                        // Numeric subfields are for control purposes and should not
                        // be displayed:
                        if (!is_numeric($subfield['code'])) {
                            $current[] = $subfield['data'];
                        }
                    }
                    // If we found at least one chunk, add a heading to our result:
                    if (!empty($current)) {
                        /*
                        if ($extended) {
                            $sourceIndicator = $result->getIndicator(2);
                            $source = '';
                            if (isset($this->subjectSources[$sourceIndicator])) {
                                $source = $this->subjectSources[$sourceIndicator];
                            } else {
                                $source = $result->getSubfield('2');
                                if ($source) {
                                    $source = $source->getData();
                                }
                            }
                            $retval[] = [
                                'heading' => $current,
                                'type' => $fieldType,
                                'source' => $source ?: ''
                            ];

                        } else {
                            $retval[] = $current;
                        }
                         *
                         */
                      $retval[] = $current;
                    }
                }
            }
        }

        // THIS IS WHERE THE KRIMDOK CODE STARTS => for 689 and LOK 689
        $results = $this->getMarcReader()->getFields('689');
        if ($results) {
            $current = [];
            $currentID = 0;
            foreach ($results as $result) {
                $id = $result->getIndicator(1);
                if ($id != $currentID && !empty($current)) {
                    $retval[] = $current;
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
                $retval[] = $current;
            }
        }
        $results = $this->getMarcReader()->getFields('LOK');
        if ($results) {
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
                    $retval[] = $current;
                }
            }
        }

        // RETURNING IS SAME AS VUFIND4
        // Remove duplicates and then send back everything we collected:
        return array_map(
            'unserialize', array_unique(array_map('serialize', $retval))
        );
    }
}
