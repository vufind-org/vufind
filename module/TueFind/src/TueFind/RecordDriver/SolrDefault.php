<?php

/**
 * Generic class for Solr RecordDriver extensions
 *
 * Since multiple inheritance is not allowed in PHP, please use the following
 * class tree structure:
 *
 * VuFind\RecordDriver\SolrDefault
 * VuFind\RecordDriver\SolrMarc
 * TueFind\RecordDriver\SolrDefault
 * TueFind\RecordDriver\SolrMarc
 * <custom>\RecordDriver\SolrDefault
 * <custom>\RecordDriver\SolrMarc
 *
 * ... where <custom> might be krimDok, ixTheo, and so on.
 * So SolrDefault should always inherit from SolrMarc of the top level.
 */

namespace TueFind\RecordDriver;
use Interop\Container\ContainerInterface;

class SolrDefault extends \VuFind\RecordDriver\SolrMarc
{
    protected $container;

    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Get all non-standardized topics
     */
    public function getAllNonStandardizedSubjectHeadings()
    {
        return (isset($this->fields['topic_non_standardized'])) ?
            $this->fields['topic_non_standardized'] : '';
    }

    /**
     * Get all standardized topics including KWCs
     */

    public function getAllStandardizedSubjectHeadings()
    {
        return (isset($this->fields['topic_standardized'])) ?
            $this->fields['topic_standardized'] : '';
    }

    public function getAllSubjectHeadingsFlat()
    {
        $result     = array();
        $headings   = $this->getAllSubjectHeadings();
        foreach($headings as $heading_arr) {
            $result = array_merge($result, $heading_arr);
        }
        return $result;
    }

    public function getAuthorsAsString() {
        $author_implode = function ($array) {
            if (is_null($array)) {
                return null;
            }
            return implode(", ", array_filter($array, function($entry) {
                return empty($entry) ? false : true;
            }));
        };
        return $author_implode(array_map($author_implode, array_map("array_keys", $this->getDeduplicatedAuthors())));
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
            foreach ($this->fields['container_ids_and_titles'] as $id_and_title) {
                $a = explode(chr(0x1F), str_replace("#31;", chr(0x1F), $id_and_title), 3);
                if (count($a) == 3) {
                    $retval[$a[0]] = array($a[1], $a[2]);
                }
            }
        }
        return $retval;
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
     * Get the mediatype
     */
    public function getMediaType()
    {
        return (isset($this->fields['mediatype'])) ?
            $this->fields['mediatype'] : '';
    }

    public function getOtherTitles() {
        return isset($this->fields['other_titles']) ?
            $this->fields['other_titles'] : array();
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

    public function getRecordDriverByPPN($ppn) {
        $recordLoader = $this->container->get('VuFind\RecordLoader');
        return $recordLoader->load($ppn, 'Solr', false);
    }

    /**
     * Get the record ID of the current record.
     *
     * @return string
     */
    public function getRecordId()
    {
        return isset($this->fields['id']) ?
            $this->fields['id'] : '';
    }

    public function getReviewedRecords()
    {
        $retval = array();
        if (isset($this->fields['reviewed_records']) && !empty($this->fields['reviewed_records'])) {
            foreach ($this->fields['reviewed_records'] as $review) {
                $a = explode(chr(0x1F), str_replace("#31;", chr(0x1F), $review), 3);
                if (count($a) == 3) {
                    $retval[$a[0]] = array($a[1], $a[2]);
                }
            }
        }
        return $retval;
    }

    public function getReviews()
    {
        $retval = array();
        if (isset($this->fields['reviews']) && !empty($this->fields['reviews'])) {
            foreach ($this->fields['reviews'] as $review) {
                $a = explode(chr(0x1F), str_replace("#31;", chr(0x1F), $review), 3);
                if (count($a) == 3) {
                    $retval[$a[0]] = array($a[1], $a[2]);
                }
            }
        }
        return $retval;
    }

    private function isOpenAccess(): bool
    {
        return isset($this->fields['is_open_access']) && $this->fields['is_open_access'];
    }

    public function getSubitoURL($broker_id) {
        // Suppress Subito links for open access items:
        if ($this->isOpenAccess())
	    return "";

        $base_url = "http://www.subito-doc.de/preorder/?BI=" . $broker_id;
        switch ($this->getBibliographicLevel()) {
            case 'Monograph':
                $isbn = $this->getCleanISBN();
                if (!empty($isbn))
                    return $base_url . "&SB=" . $isbn;
                return $base_url . "&CAT=SWB&ND" . $this->getRecordId();
            case 'Serial':
                $zdb_number = $this->getZDBNumber();
                if (!empty($zdb_number))
                    return $base_url . "&ND=" . $zdb_number;
                $issn = $this->getCleanISSN();
                if (!empty($issn))
                   return $base_url . "&SS=" . $issn;
                break;
            case 'MonographPart':
            case 'SerialPart':
                $isbn = $this->getCleanISBN();
                $issn = $this->getCleanISSN();
                $title = $this->getTitle();
                $authors = $this->getDeduplicatedAuthors();
                $page_range = $this->getPageRange();
                $volume = $this->getVolume();
                $issue = $this->getIssue();
                $year = $this->getYear();
                if ((!empty($isbn) || !empty($issn)) && !empty($title) && !empty($authors) && !empty($page_range)
                    && (!empty($volume) || !empty($issue)) && !empty($year))
                {
                    $title = $this->escapeHtml($title);
                    $author_list = "";
                    foreach ($authors as $author) {
                        if (!empty($author_list))
                            $author_list .= "%3B";
                        $author_list .= $this->escapeHtml($author);
                    }
                    $page_range = $this->escapeHtml($page_range);

                    $volume_and_or_issue = $this->escapeHtml($volume);
                    if (!empty($volume_and_or_issue))
                        $volume_and_or_issue .= "%2F";
                    $volume_and_or_issue .= $this->escapeHtml($issue);

                    return $base_url . (!empty($isbn) ? "&SB=" . $isbn : "&SS=" . $issn) . "&ATI=" . $title . "&AAU="
                        . $author_list . "&PG=" . $page_range . "&APY=" . $year . "&VOL=" . $volume_and_or_issue;
                }
        }

        return "";
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
        if (!empty($subtitle)) {
            if ($title != '') {
                $separator = preg_match("/^[\\s=]+/", $subtitle) ? " " : ": ";
                $title .= $separator;
            }
            $title .= $subtitle;
        }
        if (!empty($titleSection)) {
            if ($title != '') {
                $title .= ' / ';
            }
            $title .= $titleSection;
        }
        return $title;
    }

   /**
     * Get the title and try to reconstruct the original title for merged records
     *
     * @return string
     */
    public function getUnmergedTitleByType(string $type) : string
    {
        $merge_match_expression = "/^(.*) \/ (\(electronic\)|\(print\)); (.*) \/ (\(electronic\)|\(print\))$/";
        $title = $this->getShortTitle();
        if (preg_match($merge_match_expression, $title, $matches))
            $title = ($matches[2] == "($type)") ? $matches[1] : $matches[3];

        $subtitle = $this->getSubtitle();
        if (preg_match($merge_match_expression, $subtitle, $matches))
            $subtitle = ($matches[2] == "($type)") ? $matches[1] : $matches[3];
        $titleSection = $this->getTitleSection();
        if (!empty($subtitle)) {
            if ($title != '') {
                $separator = preg_match("/^[\\s=]+/", $subtitle) ? " " : ": ";
                $title .= $separator;
            }
            $title .= $subtitle;
        }
        if (!empty($titleSection)) {
            if ($title != '') {
                $title .= ' / ';
            }
            $title .= $titleSection;
        }
        return $title;
    }


    /**
     * Get the title or only the reconstruction of the electronic title if it is a merged record
     *
     * @return string
     */
    public function getUnmergedElectronicTitle() : string
    {
        return $this->getUnmergedTitleByType("electronic");
    }


    /**
     * Get the title or only the reconstruction of the print title if it is a merged record
     *
     * @return string
     */
    public function getUnmergedPrintTitle() : string
    {
        return $this->getUnmergedTitleByType("print");
    }


    /**
     * Normalize common german media type terms to English for the integration in the translation process
     *
     */
    public function normalizeGermanMaterialTypeTerms(string $material_type) : string
    {
        $translations = [
          "Kostenfrei" => "Free Access",
          "Vermutlich kostenfreier Zugang" => "Presumably Free Access",
          "Inhaltsverzeichnis" => "TOC",
          "Klappentext" => "blurb",
          "Rezension" => "review",
          "Cover" => "cover",
          "Inhaltstext" => "contents",
          "Verlagsinformation" => "publisher information",
          "Ausführliche Beschreibung" => "detailed description",
          "Unbekanntes Material" => "unknown material type",
        ];

        if (array_key_exists($material_type, $translations))
            return $translations[$material_type];
        return $material_type;
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
                    $retval[substr($url_and_material_type, 0, $last_colon_pos)] = $this->normalizeGermanMaterialTypeTerms($material_type);
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

    public function getZDBNumber()
    {
        return (isset($this->fields['zdb_number'])) ?
            $this->fields['zdb_number'] : '';
    }

    public function isSuperiorWork() {
        return (isset($this->fields['is_superior_work'])) ? $this->fields['is_superior_work'] : false;
    }

    public function isSubscribable() {
        return (isset($this->fields['is_subscribable'])) ? $this->fields['is_subscribable'] : false;
    }

    public function stripTrailingDates($text) {
        $matches = array();
        if (!preg_match("/(\\D*)(\\d{4}).*/", $text, $matches))
            return $text;
        return rtrim($matches[1]);
    }

    public function subscribe($params, $user)
    {
        if (!$user) {
            throw new LoginRequiredException('You must be logged in first');
        }

        $table = $this->getDbTable('Subscription');
        $recordId = $this->getUniqueId();
        $userId = $user->id;

        if ($table->findExisting($userId, $recordId)) {
            return "Exists";
        }
        return $table->subscribe($userId, $recordId, $this->getTitle(), $this->getAuthorsAsString(), $this->getPublicationDates()[0]);
    }

    public function unsubscribe($params, $user)
    {
        if (!$user) {
            throw new LoginRequiredException('You must be logged in first');
        }

        $table = $this->getDbTable('Subscription');
        $recordId = $this->getUniqueId();
        $userId = $user->id;

        return $table->unsubscribe($userId, $recordId);
    }

    public function pdaSubscribe($params, $user, &$data)
    {
        if (!$user) {
            throw new LoginRequiredException('You must be logged in first');
        }

        $table = $this->getDbTable('PDASubscription');
        $recordId = $this->getUniqueId();
        $userId = $user->id;

        if ($table->findExisting($userId, $recordId)) {
            return "Exists";
        }

        $data = [$userId, $recordId, $this->getTitle(), $this->getAuthorsAsString(), $this->getPublicationDates()[0], $this->getISBNs()[0]];
        return call_user_func_array([$table, "subscribe"], $data);
    }

    public function pdaUnsubscribe($params, $user)
    {
        if (!$user) {
            throw new LoginRequiredException('You must be logged in first');
        }

        $table = $this->getDbTable('PDASubscription');
        $recordId = $this->getUniqueId();
        $userId = $user->id;

        return $table->unsubscribe($userId, $recordId);
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
}
