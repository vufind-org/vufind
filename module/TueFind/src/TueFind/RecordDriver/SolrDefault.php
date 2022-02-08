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
use VuFind\Exception\LoginRequired as LoginRequiredException;
use VuFind\Exception\RecordMissing as RecordMissingException;

class SolrDefault extends \VuFind\RecordDriver\SolrMarc
{
    const SUBITO_BROKER_ID = 'TUEFIND';

    protected $container;

    protected $selected_fulltext_types;
    protected $hasFulltextMatch;

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

    public function getAuthorsAsString()
    {
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
     * Compatibility function for VuFind's RecordDriver, e.g. for MetadataVocabularies
     * (renamed solr field)
     *
     * @return string
     */
    public function getContainerEndPage()
    {
        return $this->fields['end_page'] ?? '';
    }

    /**
     * Compatibility function for VuFind's RecordDriver, e.g. for MetadataVocabularies
     * (renamed solr field)
     *
     * @return string
     */
    public function getContainerIssue()
    {
        return $this->fields['issue'] ?? '';
    }

    /**
     * Compatibility function for VuFind's RecordDriver, e.g. for MetadataVocabularies
     * (renamed solr field)
     *
     * @return string
     */
    public function getContainerStartPage()
    {
        return $this->fields['start_page'] ?? '';
    }

    /**
     * Compatibility function for VuFind's RecordDriver, e.g. for MetadataVocabularies
     * (different solr field, return only title of first container)
     *
     * @return string
     */
    public function getContainerTitle()
    {
        $containerIdsAndTitles = $this->getContainerIDsAndTitles();
        foreach($containerIdsAndTitles as $ppn => $containerTitleAndVolume) {
            if (!empty($containerTitleAndVolume[0]))
                return $containerTitleAndVolume[0];
        }
        return '';
    }

    /**
     * Compatibility function for VuFind's RecordDriver, e.g. for MetadataVocabularies
     * (renamed solr field)
     *
     * @return string
     */
    public function getContainerVolume()
    {
        return $this->fields['volume'] ?? '';
    }

    /**
     * Return an associative array of all container IDs (parents) mapped to their titles containing the record.
     *
     * @return array ($ppn => [0 => $title, 1 => $volume])
     */
    public function getContainerIDsAndTitles()
    {
        $retval = [];
        if (isset($this->fields['container_ids_and_titles']) && !empty($this->fields['container_ids_and_titles'])) {
            foreach ($this->fields['container_ids_and_titles'] as $id_and_title) {
                $a = explode(chr(0x1F), str_replace("#31;", chr(0x1F), $id_and_title), 3);
                if (count($a) == 3) {
                    $retval[$a[0]] = [$a[1], $a[2]];
                }
            }
        }
        return $retval;
    }

    public function getCorporateAuthorsGnds(): array
    {
        return $this->fields['author2_gnd'] ?? [];
    }

    public function getCorporateAuthorsIds(): array
    {
        return $this->fields['author2_id'] ?? [];
    }

    public function getDeduplicatedAuthors($dataFields = ['role', 'id', 'gnd'])
    {
        return parent::getDeduplicatedAuthors($dataFields);
    }

    public function getFollowingPPNAndTitle()
    {
        $retval = [];
        if (!empty($this->fields['following_ppn_and_title']))
            $retval = explode(':', $this->fields['following_ppn_and_title'], 2);
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
     * Get the mediatypes
     */
    public function getMediaTypes()
    {
        return (isset($this->fields['mediatype'])) ?
            $this->fields['mediatype'] : array();
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

    public function getPrecedingPPNAndTitle()
    {
        $retval = [];
        if (!empty($this->fields['preceding_ppn_and_title']))
            $retval = explode(':', $this->fields['preceding_ppn_and_title'], 2);
        return $retval;
    }

    public function getPrimaryAuthorsGnds(): array
    {
        return $this->fields['author_gnd'] ?? [];
    }

    public function getPrimaryAuthorsIds(): array
    {
        return $this->fields['author_id'] ?? [];
    }

    /**
     * Same as the parent, but we want to return not only the author's name,
     * but also ids and other properties (e.g. to generate links to authority pages).
     */
    public function getPrimaryAuthorsWithHighlighting(): array
    {
        $highlights = [];
        // Create a map of de-highlighted valeus => highlighted values.
        foreach ($this->getRawAuthorHighlights() as $current) {
            $dehighlighted = str_replace(
                ['{{{{START_HILITE}}}}', '{{{{END_HILITE}}}}'], '', $current
            );
            $highlights[$dehighlighted] = $current;
        }

        // replace unhighlighted authors with highlighted versions where
        // applicable:
        $authors = [];
        foreach ($this->getDeduplicatedAuthors()['primary'] ?? [] as $author => $authorProperties) {
            $author = $highlights[$author] ?? $author;
            $authors[$author] = $authorProperties;
        }
        return $authors;
    }

    public function getRecordDriverByPPN($ppn) {
        $recordLoader = $this->container->get('VuFind\RecordLoader');
        return $recordLoader->load($ppn, 'Solr', false);
    }

    public function getSecondaryAuthorsGnds(): array
    {
        return $this->fields['author2_gnd'] ?? [];
    }

    public function getSecondaryAuthorsIds(): array
    {
        return $this->fields['author2_id'] ?? [];
    }

    public function getSuperiorPPN() {
        return isset($this->fields['superior_ppn']) ?
            $this->fields['superior_ppn'][0] : '';
    }


    public function getSuperiorRecord()
    {
        $superior_ppn = $this->getSuperiorPPN();
        if (empty($superior_ppn))
            return NULL;

        try {
            return $this->getRecordDriverByPPN($superior_ppn);
        } catch (RecordMissingException $e) {
            return NULL;
        }
    }


    public function getSuperiorFormats()
    {
        $superior_record = $this->getSuperiorRecord();
        if ($superior_record == NULL) {
            return '';
        }
        return $superior_record->getFormats();
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

    public function isOpenAccess(): bool
    {
        return isset($this->fields['is_open_access']) && ($this->fields['is_open_access'] == 'open-access');
    }

    /**
     * @return string
     */
    public function getPageRange()
    {
        return isset($this->fields['page_range']) ? $this->fields['page_range'] : '';
    }

    public function getSubitoURL() {
        // Suppress Subito links for open access items:
        if ($this->isOpenAccess())
	    return "";

        $base_url = "http://www.subito-doc.de/preorder/?BI=" . static::SUBITO_BROKER_ID;
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
                    $title = urlencode($title);
                    $author_list = "";
                    foreach ($authors as $author) {
                        if (!empty($author_list))
                            $author_list .= "%3B";
                        $author_list .= urlencode($author);
                    }
                    $page_range = urlencode($page_range);

                    $volume_and_or_issue = urlencode($volume);
                    if (!empty($volume_and_or_issue))
                        $volume_and_or_issue .= "%2F";
                    $volume_and_or_issue .= urlencode($issue);

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
     * Return a list of translated topics. Can be used e.g. for chart generation.
     * (translation handling only possible in IxTheo right now.)
     */
    public function getTopicsForCloud($language=null): array
    {
        return array_unique($this->fields['topic_cloud'] ?? []);
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

    /**
     * Check whether a record is available for PDA
     * - Default false
     * - implemented differently in IxTheo and KrimDok, so should be overridden there
     *
     * @return bool
     */
    public function isAvailableForPDA()
    {
        return false;
    }

    public function isSuperiorWork()
    {
        return (isset($this->fields['is_superior_work'])) ? $this->fields['is_superior_work'] : false;
    }

    public function hasInferiorWorksInCurrentSubsystem()
    {
        $subsystem = $this->container->get('ViewHelperManager')->get('tuefind')->getTueFindSubtype();
        if (($subsystem == 'IXT' || $subsystem == 'KRI') && $this->fields['is_superior_work'])
	    return true;
        if (!isset($this->fields['superior_work_subsystems']))
            return false;

        $subsystems = $this->fields['superior_work_subsystems'];
        return in_array($subsystem, $subsystems, true);
    }

    public function isSubscribable()
    {
        return (isset($this->fields['is_subscribable'])) ? $this->fields['is_subscribable'] : false;
    }

    public function stripTrailingDates($text) {
        $matches = [];
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
    public function getPublicationDetailsNoPlaces()
    {
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
     * Check whether there are fulltexts associated with this record
     * @return bool
     */
    public function hasFulltext()
    {
        return isset($this->fields['has_fulltext']) && $this->fields['has_fulltext'] == true;
    }


    public function setHasFulltextMatch()
    {
        $this->hasFulltextMatch = true;
    }


    public function hasFulltextMatch()
    {
        return $this->hasFulltextMatch ?? false;
    }


    public function getFulltextTypes() : array
    {
        return (isset($this->fields['fulltext_types'])) ? $this->fields['fulltext_types'] : [];
    }


    public function setFulltextTypeFilters($selected_fulltext_types)
    {
        $this->selected_fulltext_types = $selected_fulltext_types;
    }


    public function getFulltextTypeFilters()
    {
        return $this->selected_fulltext_types;
    }


    public function isHybrid()
    {
        return isset($this->fields['is_hybrid']) && $this->fields['is_hybrid'] == true;
    }
}
