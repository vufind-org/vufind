<?php

namespace KrimDok\RecordDriver;

class SolrMarc extends \TuFind\RecordDriver\SolrMarc
{
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
            $results = $this->getMarcRecord()->getFields($field);
            if (!$results) {
                continue;
            }

            // If we got here, we found results -- let's loop through them.
            foreach ($results as $result) {
                // Start an array for holding the chunks of the current heading:
                $current = [];

                // Get all the chunks and collect them together:
                $subfields = $result->getSubfields();
                if ($subfields) {
                    foreach ($subfields as $subfield) {
                        // Numeric subfields are for control purposes and should not
                        // be displayed:
                        if (!is_numeric($subfield->getCode())) {
                            $current[] = $subfield->getData();
                        }
                    }
                    // If we found at least one chunk, add a heading to our result:
                    if (!empty($current)) {
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
                    }
                }
            }
        }

        // THIS IS WHERE THE KRIMDOK CODE STARTS => for 689 and LOK 689
        $results = $this->getMarcRecord()->getFields('689');
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
        $results = $this->getMarcRecord()->getFields('LOK');
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

    public function getGenres()
    {
        return isset($this->fields['genre']) ? $this->fields['genre'] : array();
    }

    /**
     * @return array
     */
    public function getFidSystematik()
    {
        if (isset($this->fields['fid_systematik']) && !empty($this->fields['fid_systematik'])) {
            return $this->fields['fid_systematik'];
        } else {
            return array();
        }
    }

    /**
     * @return array
     */
    public function getInstitutsSystematik()
    {
        if (isset($this->fields['instituts_systematik2']) && !empty($this->fields['instituts_systematik2'])) {
            return $this->fields['instituts_systematik2'];
        } else {
            return array();
        }
    }

    /**
     * Get an array of all the ISILs in the record.
     *
     * @return array
     */
    public function getIsils()
    {
        return isset($this->fields['isil']) ? $this->fields['isil'] : [];
    }

    /**
     * Get local signatures of the current record.
     *
     * @return array
     */
    public function getLocalSignatures()
    {
        return isset($this->fields['local_signature']) && is_array($this->fields['local_signature']) ?
            $this->fields['local_signature'] : [];
    }

    /**
     * Get the start page of the item that contains this record (i.e. MARC 773q of a
     * journal).
     *
     * @return string
     */
    public function getPageCount()
    {
        return isset($this->fields['page_count'])
            ? $this->fields['page_count'] : '';
    }

    /**
     * @return string
     */
    public function getPageRange()
    {
        return isset($this->fields['page_range']) ? $this->fields['page_range'] : '';
    }

    /**
     * Return an array of non-empty subfield values found in the provided MARC
     * field.  If $concat is true, the array will contain either zero or one
     * entries (empty array if no subfields found, subfield values concatenated
     * together in specified order if found).  If concat is false, the array
     * will contain a separate entry for each subfield value found.
     *
     * @param object $currentField Result from File_MARC::getFields.
     * @param array  $subfields    The MARC subfield codes to read
     * @param bool   $concat       Should we concatenate subfields?
     * @param string $separator    Separator string (used only when $concat === true)
     *
     * @return array
     */
    protected function getSubfieldArray($currentField, $subfields, $concat = true,
        $separator = ' '
    ) {
        // Start building a line of text for the current field
        $matches = [];

        // Loop through all subfields, collecting results that match the whitelist;
        // note that it is important to retain the original MARC order here!
        $allSubfields = $currentField->getSubfields();
        if (!empty($allSubfields)) {
            foreach ($allSubfields as $currentSubfield) {
                if (in_array($currentSubfield->getCode(), $subfields)) {
                    // Grab the current subfield value and act on it if it is
                    // non-empty:
                    $data = trim($currentSubfield->getData());
                    if (!empty($data)) {
                        $matches[] = $data;
                    }
                }
            }
        }

        // Send back the data in a different format depending on $concat mode:
        return $concat && $matches ? [implode($separator, $matches)] : $matches;
    }

    public function getTopics()
    {
        return isset($this->fields['topic']) ? $this->fields['topic'] : array();
    }

    /**
     * Return an associative array of URL's mapped to their material types.
     *
     * @return array
     */
    public function getURLsAndMaterialTypes()
    {
        $map_to_english = [
            "Inhaltsverzeichnis" => "TOC",
            "Klappentext" => "blurb",
            "Rezension" => "review",
            "Cover" => "cover",
            "Inhaltstext" => "contents",
            "Verlagsinformation" => "publisher information",
            "Ausführliche Beschreibung" => "detailed description",
            "Unbekanntes Material" => "unknown material type",
        ];
        $map_to_french = [
            "Inhaltsverzeichnis" => "contenu",
            "Klappentext" => "blurb",
            "Rezension" => "examen",
            "Cover" => "couverture",
            "Inhaltstext" => "contenu du texte",
            "Verlagsinformation" => "informations editeur",
            "Ausführliche Beschreibung" => "la pleine description",
            "Unbekanntes Material" => "matériau inconnu",
        ];

        // Determine language code:
        $lang = !is_null($this->translator)
            ? substr($this->translator->getLocale(), 0, 2)
            : 'de';

        $retval = array();

        if (isset($this->fields['urls_and_material_types']) && !empty($this->fields['urls_and_material_types'])) {
            foreach ($this->fields['urls_and_material_types'] as $url_and_material_type) {
                $last_colon_pos = strrpos($url_and_material_type, ":");
                if ($last_colon_pos) {
                    $material_type = substr($url_and_material_type, $last_colon_pos + 1);
                    if ($lang === "en") {
                        if (isset($map_to_english[$material_type]))
                            $material_type = $map_to_english[$material_type];
                    } else if ($lang == "fr") {
                        if (isset($map_to_french[$material_type]))
                            $material_type = $map_to_french[$material_type];
                    }

                    $retval[substr($url_and_material_type, 0, $last_colon_pos)] = $material_type;
                }
            }
        }

        return $retval;
    }

    public function isAvailableInTuebingen()
    {
        return (isset($this->fields['available_in_tubingen']) ? $this->fields['available_in_tubingen'] : false);
    }
}
