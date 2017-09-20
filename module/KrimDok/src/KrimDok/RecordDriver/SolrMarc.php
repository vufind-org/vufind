<?php

namespace KrimDok\RecordDriver;

class SolrMarc extends \TuFind\RecordDriver\SolrMarc
{
    /**
     * Get all subject headings associated with this record.  Each heading is
     * returned as an array of chunks, increasing from least specific to most
     * specific.
     *
     * @return array
     */
    public function getAllSubjectHeadings()
    {
        // These are the fields that may contain subject headings:
        $fields = [
            '600', '610', '611', '630', '648', '650', '651', '653', '655', '656'
        ];

        // This is all the collected data:
        $retval = [];

        // Try each MARC field one at a time:
        foreach ($fields as $field) {
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
                        $retval[] = $current;
                    }
                }
            }
        }
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
                            $current[] = $subfield->getData();
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
                            $current[] = $subfield->getData();
                        }
                    }
                }
                if (!empty($current)) {
                    $retval[] = $current;
                }
            }
        }
        // Remove duplicates and then send back everything we collected:
        return array_map(
            'unserialize', array_unique(array_map('serialize', $retval))
        );
    }

    /**
     * Return an associative array of all containee IDs (children) mapped to their titles containing the record.
     *
     * @return array
     */
    public function getContaineeIDsAndTitles()
    {
        $retval = array();

        if (isset($this->fields['containee_ids_and_titles']) && !empty($this->fields['containee_ids_and_titles'])) {
            foreach ($this->fields['containee_ids_and_titles'] as $id_and_title) {
                $a = explode(":", $id_and_title, 2);
                if (count($a) == 2) {
                    $retval[$a[0]] = $a[1];
                }
            }
        }

        return $retval;
    }

    /**
     * Return an associative array of all container IDs (parents) mapped to their titles containing the record.
     *
     * @return array
     */
    public function getContainerIDsAndTitles()
    {
        $retval = array();

        if (isset($this->fields['container_ids_and_titles']) && !empty($this->fields['container_ids_and_titles'])) {
            foreach ($this->fields['container_ids_and_titles'] as $id_title_and_volume) {
                $a = explode(hex2bin("1f"), $id_title_and_volume, 3);
                if (count($a) == 3) {
                    $retval[$a[0]] = [$a[1], $a[2]];
                }
            }
        }

        return $retval;
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
     * Get the issue of the current record.
     *
     * @return string
     */
    public function getIssue()
    {
        return isset($this->fields['issue']) ?
            $this->fields['issue'] : '';
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
     * Get the pages of the current record.
     *
     * @return string
     */
    public function getPages()
    {
        return isset($this->fields['pages']) ?
            $this->fields['pages'] : '';
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

    /**
     * Get the volume of the current record.
     *
     * @return string
     */
    public function getVolume()
    {
        return isset($this->fields['volume']) ?
            $this->fields['volume'] : '';
    }

    /**
     * Get the year of the current record.
     *
     * @return string
     */
    public function getYear()
    {
        return isset($this->fields['year']) ?
            $this->fields['year'] : '';
    }

    public function isAvailableInTuebingen()
    {
        return $this->fields['available_in_tubingen'];
    }
}
