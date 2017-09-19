<?php

namespace IxTheo\RecordDriver;

class SolrDefault extends \TueLib\RecordDriver\SolrMarc
{
    /**
     * Get a highlighted corporation string, if available.
     *
     * @return string
     */
    public function getHighlightedCorporation(){
        // Don't check for highlighted values if highlighting is disabled:
        if (!$this->highlight) {
            return '';
        }
        return (isset($this->highlightDetails['corporation'][0]))
            ? $this->highlightDetails['corporation'][0] : '';
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
     * Get the full title of the record.
     *
     * @return string
     */
    public function getTitle()
    {
        $title = $this->getShortTitle();
        $subtitle = $this->getSubtitle();
        $titleSection = $this->getTitleSection();
        if (!empty($subtitle)) { $title .= ' : ' . $subtitle; }
        if (!empty($titleSection)) { $title .= ' / ' . $titleSection; }
        return $title;
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

    /**
     * Get an array of publication detail lines combining information from
     * getPublicationDates(), getPublishers()
     *
     * @return array
     */
    public function getPublicationDetailsNoPlaces(){

        $names = $this->getPublishers();
        $dates = $this->getHumanReadablePublicationDates();

        $i = 0;
        $retval = [];
        while (isset($names[$i]) || isset($dates[$i])) {
            // Build objects to represent each set of data; these will
            // transform seamlessly into strings in the view layer.
            $retval[] = new \VuFind\RecordDriver\Response\PublicationDetails(
                isset($names[$i]) ? $names[$i] : '',
                isset($dates[$i]) ? $dates[$i] : '',
                null
            );
            $i++;
        }

        return $retval;
    }

    /**
     * Get secondary author and its role in a '$'-separated string
     *
     * @return array
     */
    public function getSecondaryAuthorsAndRole(){
        return isset($this->fields['author2_and_role']) ?
            $this->fields['author2_and_role'] : [];
    }

    /**
     * Get an array of all secondary authors (complementing getPrimaryAuthors()).
     *
     * @return array
     */
    public function getSecondaryAuthors()
    {
        if (!isset($this->fields['author2_and_role']))
            return [];

        $authors = array();
        foreach ($this->fields['author2_and_role'] as $author_and_roles) {
            $parts = explode('$', $author_and_roles);
            $authors[] = $parts[0];
        }

        return $authors;
    }

    /**
     * Get an array of all secondary authors roles (complementing
     * getPrimaryAuthorsRoles()).
     *
     * @return array
     */
    public function getSecondaryAuthorsRoles()
    {
        if (!isset($this->fields['author2_and_role']))
            return [];

        $roles = array();
        foreach ($this->fields['author2_and_role'] as $author_and_roles) {
            $parts = explode('$', $author_and_roles);
            $roles[] = array_slice($parts, 1);
        }

        return $roles;
    }

    /**
     * Helper function to restructure author arrays including relators
     *
     * @param array $authors Array of authors
     * @param array $roles   Array with relators of authors
     *
     * @return array
     */
    protected function getAuthorRolesArray($authors = [], $roles = [])
    {
        $authorRolesArray = [];

        if (!empty($authors)) {
            foreach ($authors as $index => $author) {
                if (!isset($authorRolesArray[$author])) {
                    $authorRolesArray[$author] = [];
                }
                if (isset($roles[$index]) && !empty($roles[$index])) {
                    if (is_array($roles[$index]))
                        $authorRolesArray[$author] = $roles[$index];
                    else
                        $authorRolesArray[$author][] = $roles[$index];
                }
            }
        }

        return $authorRolesArray;
    }

    /**
     * Get corporation.
     *
     * @return array
     */
    public function getCorporation()
    {
        return isset($this->fields['corporation']) ?
            $this->fields['corporation'] : [];
    }

    /**
     * Get the title of the item that contains this record (i.e. MARC 773s of a
     * journal).
     *
     * @return string
     */
    public function getJournalIssue()
    {
        return isset($this->fields['journal_issue'])
            ? $this->fields['journal_issue'] : '';
    }

    /**
     * Return an associative array of URL's mapped to their material types.
     *
     * @return array
     */
    public function getURLsAndMaterialTypes()
    {
        $retval = [];
        if (isset($this->fields['urls_and_material_types']) && !empty($this->fields['urls_and_material_types'])) {
            foreach ($this->fields['urls_and_material_types'] as $url_and_material_type) {
                $last_colon_pos = strrpos($url_and_material_type, ":");
                if ($last_colon_pos) {
                    $material_type = substr($url_and_material_type, $last_colon_pos + 1);
                    $retval[substr($url_and_material_type, 0, $last_colon_pos)] = $material_type;
                }
            }
        }
        return $retval;
    }
}
