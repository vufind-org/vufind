<?php
/**
 * Default model for Solr records -- used when a more specific model based on
 * the recordtype field cannot be found.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
namespace VuFind\RecordDriver;
use VuFindCode\ISBN, VuFind\View\Helper\Root\RecordLink;

/**
 * Default model for Solr records -- used when a more specific model based on
 * the recordtype field cannot be found.
 *
 * This should be used as the base class for all Solr-based record models.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 *
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 */
class SolrDefault extends AbstractBase
{
    /**
     * These Solr fields should be used for snippets if available (listed in order
     * of preference).
     *
     * @var array
     */
    protected $preferredSnippetFields = [
        'contents', 'topic'
    ];

    /**
     * These Solr fields should NEVER be used for snippets.  (We exclude author
     * and title because they are already covered by displayed fields; we exclude
     * spelling because it contains lots of fields jammed together and may cause
     * glitchy output; we exclude ID because random numbers are not helpful).
     *
     * @var array
     */
    protected $forbiddenSnippetFields = [
        'author', 'title', 'title_short', 'title_full',
        'title_full_unstemmed', 'title_auth', 'title_sub', 'spelling', 'id',
        'ctrlnum', 'author_variant', 'author2_variant'
    ];

    /**
     * These are captions corresponding with Solr fields for use when displaying
     * snippets.
     *
     * @var array
     */
    protected $snippetCaptions = [];

    /**
     * Should we highlight fields in search results?
     *
     * @var bool
     */
    protected $highlight = false;

    /**
     * Should we include snippets in search results?
     *
     * @var bool
     */
    protected $snippet = false;

    /**
     * Hierarchy driver plugin manager
     *
     * @var \VuFind\Hierarchy\Driver\PluginManager
     */
    protected $hierarchyDriverManager = null;

    /**
     * Hierarchy driver for current object
     *
     * @var \VuFind\Hierarchy\Driver\AbstractBase
     */
    protected $hierarchyDriver = null;

    /**
     * Highlighting details
     *
     * @var array
     */
    protected $highlightDetails = [];

    /**
     * Search results plugin manager
     *
     * @var \VuFindSearch\Service
     */
    protected $searchService = null;

    /**
     * Should we use hierarchy fields for simple container-child records linking?
     *
     * @var bool
     */
    protected $containerLinking = false;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $mainConfig     VuFind main configuration (omit for
     * built-in defaults)
     * @param \Zend\Config\Config $recordConfig   Record-specific configuration file
     * (omit to use $mainConfig as $recordConfig)
     * @param \Zend\Config\Config $searchSettings Search-specific configuration file
     */
    public function __construct($mainConfig = null, $recordConfig = null,
        $searchSettings = null
    ) {
        // Turn on highlighting/snippets as needed:
        $this->highlight = !isset($searchSettings->General->highlighting)
            ? false : $searchSettings->General->highlighting;
        $this->snippet = !isset($searchSettings->General->snippets)
            ? false : $searchSettings->General->snippets;

        // Load snippet caption settings:
        if (isset($searchSettings->Snippet_Captions)
            && count($searchSettings->Snippet_Captions) > 0
        ) {
            foreach ($searchSettings->Snippet_Captions as $key => $value) {
                $this->snippetCaptions[$key] = $value;
            }
        }

        // Container-contents linking
        $this->containerLinking
            = !isset($mainConfig->Hierarchy->simpleContainerLinks)
            ? false : $mainConfig->Hierarchy->simpleContainerLinks;

        parent::__construct($mainConfig, $recordConfig);
    }

    /**
     * Get highlighting details from the object.
     *
     * @return array
     */
    public function getHighlightDetails()
    {
        return $this->highlightDetails;
    }

    /**
     * Add highlighting details to the object.
     *
     * @param array $details Details to add
     *
     * @return void
     */
    public function setHighlightDetails($details)
    {
        $this->highlightDetails = $details;
    }

    /**
     * Get access restriction notes for the record.
     *
     * @return array
     */
    public function getAccessRestrictions()
    {
        // Not currently stored in the Solr index
        return [];
    }

    /**
     * Get all subject headings associated with this record.  Each heading is
     * returned as an array of chunks, increasing from least specific to most
     * specific.
     *
     * @return array
     */
    public function getAllSubjectHeadings()
    {
        $headings = [];
        foreach (['topic', 'geographic', 'genre', 'era'] as $field) {
            if (isset($this->fields[$field])) {
                $headings = array_merge($headings, $this->fields[$field]);
            }
        }

        // The Solr index doesn't currently store subject headings in a broken-down
        // format, so we'll just send each value as a single chunk.  Other record
        // drivers (i.e. MARC) can offer this data in a more granular format.
        $callback = function ($i) {
            return [$i];
        };
        return array_map($callback, array_unique($headings));
    }

    /**
     * Get all record links related to the current record. Each link is returned as
     * array.
     * NB: to use this method you must override it.
     * Format:
     * <code>
     * array(
     *        array(
     *               'title' => label_for_title
     *               'value' => link_name
     *               'link'  => link_URI
     *        ),
     *        ...
     * )
     * </code>
     *
     * @return null|array
     */
    public function getAllRecordLinks()
    {
        return null;
    }

    /**
     * Get award notes for the record.
     *
     * @return array
     */
    public function getAwards()
    {
        // Not currently stored in the Solr index
        return [];
    }

    /**
     * Get notes on bibliography content.
     *
     * @return array
     */
    public function getBibliographyNotes()
    {
        // Not currently stored in the Solr index
        return [];
    }

    /**
     * Get text that can be displayed to represent this record in
     * breadcrumbs.
     *
     * @return string Breadcrumb text to represent this record.
     */
    public function getBreadcrumb()
    {
        return $this->getShortTitle();
    }

    /**
     * Get the first call number associated with the record (empty string if none).
     *
     * @return string
     */
    public function getCallNumber()
    {
        $all = $this->getCallNumbers();
        return isset($all[0]) ? $all[0] : '';
    }

    /**
     * Get all call numbers associated with the record (empty string if none).
     *
     * @return array
     */
    public function getCallNumbers()
    {
        return isset($this->fields['callnumber-raw'])
            ? $this->fields['callnumber-raw'] : [];
    }

    /**
     * Return the first valid DOI found in the record (false if none).
     *
     * @return mixed
     */
    public function getCleanDOI()
    {
        $field = 'doi_str_mv';
        return (isset($this->fields[$field][0]) && !empty($this->fields[$field][0]))
            ? $this->fields[$field][0] : false;
    }

    /**
     * Return the first valid ISBN found in the record (favoring ISBN-10 over
     * ISBN-13 when possible).
     *
     * @return mixed
     */
    public function getCleanISBN()
    {
        // Get all the ISBNs and initialize the return value:
        $isbns = $this->getISBNs();
        $isbn13 = false;

        // Loop through the ISBNs:
        foreach ($isbns as $isbn) {
            // Strip off any unwanted notes:
            if ($pos = strpos($isbn, ' ')) {
                $isbn = substr($isbn, 0, $pos);
            }

            // If we find an ISBN-10, return it immediately; otherwise, if we find
            // an ISBN-13, save it if it is the first one encountered.
            $isbnObj = new ISBN($isbn);
            if ($isbn10 = $isbnObj->get10()) {
                return $isbn10;
            }
            if (!$isbn13) {
                $isbn13 = $isbnObj->get13();
            }
        }
        return $isbn13;
    }

    /**
     * Get just the base portion of the first listed ISSN (or false if no ISSNs).
     *
     * @return mixed
     */
    public function getCleanISSN()
    {
        $issns = $this->getISSNs();
        if (empty($issns)) {
            return false;
        }
        $issn = $issns[0];
        if ($pos = strpos($issn, ' ')) {
            $issn = substr($issn, 0, $pos);
        }
        return $issn;
    }

    /**
     * Get just the first listed OCLC Number (or false if none available).
     *
     * @return mixed
     */
    public function getCleanOCLCNum()
    {
        $nums = $this->getOCLC();
        return empty($nums) ? false : $nums[0];
    }

    /**
     * Get just the first listed UPC Number (or false if none available).
     *
     * @return mixed
     */
    public function getCleanUPC()
    {
        $nums = $this->getUPC();
        return empty($nums) ? false : $nums[0];
    }

    /**
     * Get the main corporate authors (if any) for the record.
     *
     * @return array
     */
    public function getCorporateAuthors()
    {
        return isset($this->fields['author_corporate']) ?
            $this->fields['author_corporate'] : [];
    }

    /**
     * Get an array of all main corporate authors roles.
     *
     * @return array
     */
    public function getCorporateAuthorsRoles()
    {
        return isset($this->fields['author_corporate_role']) ?
            $this->fields['author_corporate_role'] : [];
    }

    /**
     * Get the date coverage for a record which spans a period of time (i.e. a
     * journal).  Use getPublicationDates for publication dates of particular
     * monographic items.
     *
     * @return array
     */
    public function getDateSpan()
    {
        return isset($this->fields['dateSpan']) ?
            $this->fields['dateSpan'] : [];
    }

    /**
     * Deduplicate author information into associative array with main/corporate/
     * secondary keys.
     *
     * @return array
     */
    public function getDeduplicatedAuthors()
    {
        $authors = [
            'main' => $this->getAuthorRolesArray(
                $this->getPrimaryAuthors(),
                $this->getPrimaryAuthorsRoles()
            ),
            'corporate' => $this->getAuthorRolesArray(
                $this->getCorporateAuthors(),
                $this->getCorporateAuthorsRoles()
            ),
            'secondary' => $this->getAuthorRolesArray(
                $this->getSecondaryAuthors(),
                $this->getSecondaryAuthorsRoles()
            )
        ];

        // deduplicate
        $dedup = function (&$array1, &$array2) {
            if (!empty($array1) && !empty($array2)) {
                foreach ($array1 as $author => $roles) {
                    if (isset($array2[$author])) {
                        $array1[$author] = array_merge(
                            $array1[$author],
                            $array2[$author]
                        );
                        unset($array2[$author]);
                    }
                }
            }
        };

        $dedup($authors['main'], $authors['corporate']);
        $dedup($authors['secondary'], $authors['corporate']);
        $dedup($authors['main'], $authors['secondary']);

        $dedup_roles = function (&$array) {
            foreach ($array as $author => $roles) {
                if (is_array($roles)) {
                    $array[$author] = array_unique($roles);
                }
            }
        };

        $dedup_roles($authors['main']);
        $dedup_roles($authors['secondary']);
        $dedup_roles($authors['corporate']);

        return $authors;
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
                if (isset($roles[$index]) && !empty($roles[$index])
                ) {
                    $authorRolesArray[$author][] = $roles[$index];
                }
            }
        }

        return $authorRolesArray;
    }

    /**
     * Get the edition of the current record.
     *
     * @return string
     */
    public function getEdition()
    {
        return isset($this->fields['edition']) ?
            $this->fields['edition'] : '';
    }

    /**
     * Get notes on finding aids related to the record.
     *
     * @return array
     */
    public function getFindingAids()
    {
        // Not currently stored in the Solr index
        return [];
    }

    /**
     * Get an array of all the formats associated with the record.
     *
     * @return array
     */
    public function getFormats()
    {
        return isset($this->fields['format']) ? $this->fields['format'] : [];
    }

    /**
     * Get general notes on the record.
     *
     * @return array
     */
    public function getGeneralNotes()
    {
        // Not currently stored in the Solr index
        return [];
    }

    /**
     * Get highlighted author data, if available.
     *
     * @return array
     */
    public function getRawAuthorHighlights()
    {
        // Don't check for highlighted values if highlighting is disabled:
        return ($this->highlight && isset($this->highlightDetails['author']))
            ? $this->highlightDetails['author'] : [];
    }

    /**
     * Get primary author information with highlights applied (if applicable)
     *
     * @return array
     */
    public function getPrimaryAuthorsWithHighlighting()
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
        foreach ($this->getPrimaryAuthors() as $author) {
            $authors[] = isset($highlights[$author])
                ? $highlights[$author] : $author;
        }
        return $authors;
    }

    /**
     * Get a string representing the last date that the record was indexed.
     *
     * @return string
     */
    public function getLastIndexed()
    {
        return isset($this->fields['last_indexed'])
            ? $this->fields['last_indexed'] : '';
    }

    /**
     * Given a Solr field name, return an appropriate caption.
     *
     * @param string $field Solr field name
     *
     * @return mixed        Caption if found, false if none available.
     */
    public function getSnippetCaption($field)
    {
        return isset($this->snippetCaptions[$field])
            ? $this->snippetCaptions[$field] : false;
    }

    /**
     * Pick one line from the highlighted text (if any) to use as a snippet.
     *
     * @return mixed False if no snippet found, otherwise associative array
     * with 'snippet' and 'caption' keys.
     */
    public function getHighlightedSnippet()
    {
        // Only process snippets if the setting is enabled:
        if ($this->snippet) {
            // First check for preferred fields:
            foreach ($this->preferredSnippetFields as $current) {
                if (isset($this->highlightDetails[$current][0])) {
                    return [
                        'snippet' => $this->highlightDetails[$current][0],
                        'caption' => $this->getSnippetCaption($current)
                    ];
                }
            }

            // No preferred field found, so try for a non-forbidden field:
            if (isset($this->highlightDetails)
                && is_array($this->highlightDetails)
            ) {
                foreach ($this->highlightDetails as $key => $value) {
                    if (!in_array($key, $this->forbiddenSnippetFields)) {
                        return [
                            'snippet' => $value[0],
                            'caption' => $this->getSnippetCaption($key)
                        ];
                    }
                }
            }
        }

        // If we got this far, no snippet was found:
        return false;
    }

    /**
     * Get a highlighted title string, if available.
     *
     * @return string
     */
    public function getHighlightedTitle()
    {
        // Don't check for highlighted values if highlighting is disabled:
        if (!$this->highlight) {
            return '';
        }
        return (isset($this->highlightDetails['title'][0]))
            ? $this->highlightDetails['title'][0] : '';
    }

    /**
     * Get the institutions holding the record.
     *
     * @return array
     */
    public function getInstitutions()
    {
        return isset($this->fields['institution'])
            ? $this->fields['institution'] : [];
    }

    /**
     * Get an array of all ISBNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISBNs()
    {
        // If ISBN is in the index, it should automatically be an array... but if
        // it's not set at all, we should normalize the value to an empty array.
        return isset($this->fields['isbn']) && is_array($this->fields['isbn']) ?
            $this->fields['isbn'] : [];
    }

    /**
     * Get an array of all ISSNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISSNs()
    {
        // If ISSN is in the index, it should automatically be an array... but if
        // it's not set at all, we should normalize the value to an empty array.
        return isset($this->fields['issn']) && is_array($this->fields['issn']) ?
            $this->fields['issn'] : [];
    }

    /**
     * Get an array of all the languages associated with the record.
     *
     * @return array
     */
    public function getLanguages()
    {
        return isset($this->fields['language']) ?
            $this->fields['language'] : [];
    }

    /**
     * Get a LCCN, normalised according to info:lccn
     *
     * @return string
     */
    public function getLCCN()
    {
        // Get LCCN from Index
        $raw = isset($this->fields['lccn']) ? $this->fields['lccn'] : '';

        // Remove all blanks.
        $raw = preg_replace('{[ \t]+}', '', $raw);

        // If there is a forward slash (/) in the string, remove it, and remove all
        // characters to the right of the forward slash.
        if (strpos($raw, '/') > 0) {
            $tmpArray = explode("/", $raw);
            $raw = $tmpArray[0];
        }
        /* If there is a hyphen in the string:
            a. Remove it.
            b. Inspect the substring following (to the right of) the (removed)
               hyphen. Then (and assuming that steps 1 and 2 have been carried out):
                    i.  All these characters should be digits, and there should be
                    six or less.
                    ii. If the length of the substring is less than 6, left-fill the
                    substring with zeros until  the length is six.
        */
        if (strpos($raw, '-') > 0) {
            // haven't checked for i. above. If they aren't all digits, there is
            // nothing that can be done, so might as well leave it.
            $tmpArray = explode("-", $raw);
            $raw = $tmpArray[0] . str_pad($tmpArray[1], 6, "0", STR_PAD_LEFT);
        }
        return $raw;
    }

    /**
     * Get an array of newer titles for the record.
     *
     * @return array
     */
    public function getNewerTitles()
    {
        return isset($this->fields['title_new']) ?
            $this->fields['title_new'] : [];
    }

    /**
     * Get the OCLC number(s) of the record.
     *
     * @return array
     */
    public function getOCLC()
    {
        return isset($this->fields['oclc_num']) ?
            $this->fields['oclc_num'] : [];
    }

    /**
     * Support method for getOpenUrl() -- pick the OpenURL format.
     *
     * @return string
     */
    protected function getOpenUrlFormat()
    {
        // If we have multiple formats, Book, Journal and Article are most
        // important...
        $formats = $this->getFormats();
        if (in_array('Book', $formats)) {
            return 'Book';
        } else if (in_array('Article', $formats)) {
            return 'Article';
        } else if (in_array('Journal', $formats)) {
            return 'Journal';
        } else if (isset($formats[0])) {
            return $formats[0];
        } else if (strlen($this->getCleanISSN()) > 0) {
            return 'Journal';
        } else if (strlen($this->getCleanISBN()) > 0) {
            return 'Book';
        }
        return 'UnknownFormat';
    }

    /**
     * Get the COinS identifier.
     *
     * @return string
     */
    protected function getCoinsID()
    {
        // Get the COinS ID -- it should be in the OpenURL section of config.ini,
        // but we'll also check the COinS section for compatibility with legacy
        // configurations (this moved between the RC2 and 1.0 releases).
        if (isset($this->mainConfig->OpenURL->rfr_id)
            && !empty($this->mainConfig->OpenURL->rfr_id)
        ) {
            return $this->mainConfig->OpenURL->rfr_id;
        }
        if (isset($this->mainConfig->COinS->identifier)
            && !empty($this->mainConfig->COinS->identifier)
        ) {
            return $this->mainConfig->COinS->identifier;
        }
        return 'vufind.svn.sourceforge.net';
    }

    /**
     * Get default OpenURL parameters.
     *
     * @return array
     */
    protected function getDefaultOpenUrlParams()
    {
        // Get a representative publication date:
        $pubDate = $this->getPublicationDates();
        $pubDate = empty($pubDate) ? '' : $pubDate[0];

        // Start an array of OpenURL parameters:
        return [
            'url_ver' => 'Z39.88-2004',
            'ctx_ver' => 'Z39.88-2004',
            'ctx_enc' => 'info:ofi/enc:UTF-8',
            'rfr_id' => 'info:sid/' . $this->getCoinsID() . ':generator',
            'rft.title' => $this->getTitle(),
            'rft.date' => $pubDate
        ];
    }

    /**
     * Get OpenURL parameters for a book.
     *
     * @return array
     */
    protected function getBookOpenUrlParams()
    {
        $params = $this->getDefaultOpenUrlParams();
        $params['rft_val_fmt'] = 'info:ofi/fmt:kev:mtx:book';
        $params['rft.genre'] = 'book';
        $params['rft.btitle'] = $params['rft.title'];
        $series = $this->getSeries();
        if (count($series) > 0) {
            // Handle both possible return formats of getSeries:
            $params['rft.series'] = is_array($series[0]) ?
                $series[0]['name'] : $series[0];
        }
        $params['rft.au'] = $this->getPrimaryAuthor();
        $publishers = $this->getPublishers();
        if (count($publishers) > 0) {
            $params['rft.pub'] = $publishers[0];
        }
        $params['rft.edition'] = $this->getEdition();
        $params['rft.isbn'] = (string)$this->getCleanISBN();
        return $params;
    }

    /**
     * Get OpenURL parameters for an article.
     *
     * @return array
     */
    protected function getArticleOpenUrlParams()
    {
        $params = $this->getDefaultOpenUrlParams();
        $params['rft_val_fmt'] = 'info:ofi/fmt:kev:mtx:journal';
        $params['rft.genre'] = 'article';
        $params['rft.issn'] = (string)$this->getCleanISSN();
        // an article may have also an ISBN:
        $params['rft.isbn'] = (string)$this->getCleanISBN();
        $params['rft.volume'] = $this->getContainerVolume();
        $params['rft.issue'] = $this->getContainerIssue();
        $params['rft.spage'] = $this->getContainerStartPage();
        // unset default title -- we only want jtitle/atitle here:
        unset($params['rft.title']);
        $params['rft.jtitle'] = $this->getContainerTitle();
        $params['rft.atitle'] = $this->getTitle();
        $params['rft.au'] = $this->getPrimaryAuthor();

        $params['rft.format'] = 'Article';
        $langs = $this->getLanguages();
        if (count($langs) > 0) {
            $params['rft.language'] = $langs[0];
        }
        return $params;
    }

    /**
     * Get OpenURL parameters for an unknown format.
     *
     * @param string $format Name of format
     *
     * @return array
     */
    protected function getUnknownFormatOpenUrlParams($format = 'UnknownFormat')
    {
        $params = $this->getDefaultOpenUrlParams();
        $params['rft_val_fmt'] = 'info:ofi/fmt:kev:mtx:dc';
        $params['rft.creator'] = $this->getPrimaryAuthor();
        $publishers = $this->getPublishers();
        if (count($publishers) > 0) {
            $params['rft.pub'] = $publishers[0];
        }
        $params['rft.format'] = $format;
        $langs = $this->getLanguages();
        if (count($langs) > 0) {
            $params['rft.language'] = $langs[0];
        }
        return $params;
    }

    /**
     * Get OpenURL parameters for a journal.
     *
     * @return array
     */
    protected function getJournalOpenUrlParams()
    {
        $params = $this->getUnknownFormatOpenUrlParams('Journal');
        /* This is probably the most technically correct way to represent
         * a journal run as an OpenURL; however, it doesn't work well with
         * Zotero, so it is currently commented out -- instead, we just add
         * some extra fields and to the "unknown format" case.
        $params['rft_val_fmt'] = 'info:ofi/fmt:kev:mtx:journal';
        $params['rft.genre'] = 'journal';
        $params['rft.jtitle'] = $params['rft.title'];
        $params['rft.issn'] = $this->getCleanISSN();
        $params['rft.au'] = $this->getPrimaryAuthor();
         */
        $params['rft.issn'] = (string)$this->getCleanISSN();

        // Including a date in a title-level Journal OpenURL may be too
        // limiting -- in some link resolvers, it may cause the exclusion
        // of databases if they do not cover the exact date provided!
        unset($params['rft.date']);

        // If we're working with the SFX resolver, we should add a
        // special parameter to ensure that electronic holdings links
        // are shown even though no specific date or issue is specified:
        if (isset($this->mainConfig->OpenURL->resolver)
            && strtolower($this->mainConfig->OpenURL->resolver) == 'sfx'
        ) {
            $params['sfx.ignore_date_threshold'] = 1;
        }
        return $params;
    }

    /**
     * Get the OpenURL parameters to represent this record (useful for the
     * title attribute of a COinS span tag).
     *
     * @param bool $overrideSupportsOpenUrl Flag to override checking
     * supportsOpenUrl() (default is false)
     *
     * @return string OpenURL parameters.
     */
    public function getOpenUrl($overrideSupportsOpenUrl = false)
    {
        // stop here if this record does not support OpenURLs
        if (!$overrideSupportsOpenUrl && !$this->supportsOpenUrl()) {
            return false;
        }

        // Set up parameters based on the format of the record:
        $format = $this->getOpenUrlFormat();
        $method = "get{$format}OpenUrlParams";
        if (method_exists($this, $method)) {
            $params = $this->$method();
        } else {
            $params = $this->getUnknownFormatOpenUrlParams($format);
        }

        // Assemble the URL:
        return http_build_query($params);
    }

    /**
     * Get the OpenURL parameters to represent this record for COinS even if
     * supportsOpenUrl() is false for this RecordDriver.
     *
     * @return string OpenURL parameters.
     */
    public function getCoinsOpenUrl()
    {
        return $this->getOpenUrl($this->supportsCoinsOpenUrl());
    }

    /**
     * Get an array of physical descriptions of the item.
     *
     * @return array
     */
    public function getPhysicalDescriptions()
    {
        return isset($this->fields['physical']) ?
            $this->fields['physical'] : [];
    }

    /**
     * Get the item's place of publication.
     *
     * @return array
     */
    public function getPlacesOfPublication()
    {
        // Not currently stored in the Solr index
        return [];
    }

    /**
     * Get an array of playing times for the record (if applicable).
     *
     * @return array
     */
    public function getPlayingTimes()
    {
        // Not currently stored in the Solr index
        return [];
    }

    /**
     * Get an array of previous titles for the record.
     *
     * @return array
     */
    public function getPreviousTitles()
    {
        return isset($this->fields['title_old']) ?
            $this->fields['title_old'] : [];
    }

    /**
     * Get the main author of the record.
     *
     * @return string
     */
    public function getPrimaryAuthor()
    {
        $authors = $this->getPrimaryAuthors();
        return isset($authors[0]) ? $authors[0] : '';
    }

    /**
     * Get the main authors of the record.
     *
     * @return array
     */
    public function getPrimaryAuthors()
    {
        return isset($this->fields['author'])
            ? (array) $this->fields['author'] : [];
    }

    /**
     * Get an array of all main authors roles (complementing
     * getSecondaryAuthorsRoles()).
     *
     * @return array
     */
    public function getPrimaryAuthorsRoles()
    {
        return isset($this->fields['author_role']) ?
            $this->fields['author_role'] : [];
    }

    /**
     * Get credits of people involved in production of the item.
     *
     * @return array
     */
    public function getProductionCredits()
    {
        // Not currently stored in the Solr index
        return [];
    }

    /**
     * Get the publication dates of the record.  See also getDateSpan().
     *
     * @return array
     */
    public function getPublicationDates()
    {
        return isset($this->fields['publishDate']) ?
            $this->fields['publishDate'] : [];
    }

    /**
     * Get human readable publication dates for display purposes (may not be suitable
     * for computer processing -- use getPublicationDates() for that).
     *
     * @return array
     */
    public function getHumanReadablePublicationDates()
    {
        return $this->getPublicationDates();
    }

    /**
     * Get an array of publication detail lines combining information from
     * getPublicationDates(), getPublishers() and getPlacesOfPublication().
     *
     * @return array
     */
    public function getPublicationDetails()
    {
        $places = $this->getPlacesOfPublication();
        $names = $this->getPublishers();
        $dates = $this->getHumanReadablePublicationDates();

        $i = 0;
        $retval = [];
        while (isset($places[$i]) || isset($names[$i]) || isset($dates[$i])) {
            // Build objects to represent each set of data; these will
            // transform seamlessly into strings in the view layer.
            $retval[] = new Response\PublicationDetails(
                isset($places[$i]) ? $places[$i] : '',
                isset($names[$i]) ? $names[$i] : '',
                isset($dates[$i]) ? $dates[$i] : ''
            );
            $i++;
        }

        return $retval;
    }

    /**
     * Get an array of publication frequency information.
     *
     * @return array
     */
    public function getPublicationFrequency()
    {
        // Not currently stored in the Solr index
        return [];
    }

    /**
     * Get the publishers of the record.
     *
     * @return array
     */
    public function getPublishers()
    {
        return isset($this->fields['publisher']) ?
            $this->fields['publisher'] : [];
    }

    /**
     * Get an array of information about record history, obtained in real-time
     * from the ILS.
     *
     * @return array
     */
    public function getRealTimeHistory()
    {
        // Not supported by the Solr index -- implement in child classes.
        return [];
    }

    /**
     * Get an array of information about record holdings, obtained in real-time
     * from the ILS.
     *
     * @return array
     */
    public function getRealTimeHoldings()
    {
        // Not supported by the Solr index -- implement in child classes.
        return [];
    }

    /**
     * Get an array of strings describing relationships to other items.
     *
     * @return array
     */
    public function getRelationshipNotes()
    {
        // Not currently stored in the Solr index
        return [];
    }

    /**
     * Get an array of all secondary authors (complementing getPrimaryAuthors()).
     *
     * @return array
     */
    public function getSecondaryAuthors()
    {
        return isset($this->fields['author2']) ?
            $this->fields['author2'] : [];
    }

    /**
     * Get an array of all secondary authors roles (complementing
     * getPrimaryAuthorsRoles()).
     *
     * @return array
     */
    public function getSecondaryAuthorsRoles()
    {
        return isset($this->fields['author2_role']) ?
            $this->fields['author2_role'] : [];
    }

    /**
     * Get an array of all series names containing the record.  Array entries may
     * be either the name string, or an associative array with 'name' and 'number'
     * keys.
     *
     * @return array
     */
    public function getSeries()
    {
        // Only use the contents of the series2 field if the series field is empty
        if (isset($this->fields['series']) && !empty($this->fields['series'])) {
            return $this->fields['series'];
        }
        return isset($this->fields['series2']) ?
            $this->fields['series2'] : [];
    }

    /**
     * Get the short (pre-subtitle) title of the record.
     *
     * @return string
     */
    public function getShortTitle()
    {
        return isset($this->fields['title_short']) ?
            $this->fields['title_short'] : '';
    }

    /**
     * Get the item's source.
     *
     * @return string
     */
    public function getSource()
    {
        // Not supported in base class:
        return '';
    }

    /**
     * Get the subtitle of the record.
     *
     * @return string
     */
    public function getSubtitle()
    {
        return isset($this->fields['title_sub']) ?
            $this->fields['title_sub'] : '';
    }

    /**
     * Get an array of technical details on the item represented by the record.
     *
     * @return array
     */
    public function getSystemDetails()
    {
        // Not currently stored in the Solr index
        return [];
    }

    /**
     * Get an array of summary strings for the record.
     *
     * @return array
     */
    public function getSummary()
    {
        // We need to return an array, so if we have a description, turn it into an
        // array as needed (it should be a flat string according to the default
        // schema, but we might as well support the array case just to be on the safe
        // side:
        if (isset($this->fields['description'])
            && !empty($this->fields['description'])
        ) {
            return is_array($this->fields['description'])
                ? $this->fields['description'] : [$this->fields['description']];
        }

        // If we got this far, no description was found:
        return [];
    }

    /**
     * Get an array of note about the record's target audience.
     *
     * @return array
     */
    public function getTargetAudienceNotes()
    {
        // Not currently stored in the Solr index
        return [];
    }

    /**
     * Returns one of three things: a full URL to a thumbnail preview of the record
     * if an image is available in an external system; an array of parameters to
     * send to VuFind's internal cover generator if no fixed URL exists; or false
     * if no thumbnail can be generated.
     *
     * @param string $size Size of thumbnail (small, medium or large -- small is
     * default).
     *
     * @return string|array|bool
     */
    public function getThumbnail($size = 'small')
    {
        if (isset($this->fields['thumbnail']) && $this->fields['thumbnail']) {
            return $this->fields['thumbnail'];
        }
        $arr = [
            'author'     => mb_substr($this->getPrimaryAuthor(), 0, 300, 'utf-8'),
            'callnumber' => $this->getCallNumber(),
            'size'       => $size,
            'title'      => mb_substr($this->getTitle(), 0, 300, 'utf-8')
        ];
        if ($isbn = $this->getCleanISBN()) {
            $arr['isbn'] = $isbn;
        }
        if ($issn = $this->getCleanISSN()) {
            $arr['issn'] = $issn;
        }
        if ($oclc = $this->getCleanOCLCNum()) {
            $arr['oclc'] = $oclc;
        }
        if ($upc = $this->getCleanUPC()) {
            $arr['upc'] = $upc;
        }
        // If an ILS driver has injected extra details, check for IDs in there
        // to fill gaps:
        if ($ilsDetails = $this->getExtraDetail('ils_details')) {
            foreach (['isbn', 'issn', 'oclc', 'upc'] as $key) {
                if (!isset($arr[$key]) && isset($ilsDetails[$key])) {
                    $arr[$key] = $ilsDetails[$key];
                }
            }
        }
        return $arr;
    }

    /**
     * Get the full title of the record.
     *
     * @return string
     */
    public function getTitle()
    {
        return isset($this->fields['title']) ?
            $this->fields['title'] : '';
    }

    /**
     * Get the text of the part/section portion of the title.
     *
     * @return string
     */
    public function getTitleSection()
    {
        // Not currently stored in the Solr index
        return null;
    }

    /**
     * Get the statement of responsibility that goes with the title (i.e. "by John
     * Smith").
     *
     * @return string
     */
    public function getTitleStatement()
    {
        // Not currently stored in the Solr index
        return null;
    }

    /**
     * Get an array of lines from the table of contents.
     *
     * @return array
     */
    public function getTOC()
    {
        return isset($this->fields['contents'])
            ? $this->fields['contents'] : [];
    }

    /**
     * Get hierarchical place names
     *
     * @return array
     */
    public function getHierarchicalPlaceNames()
    {
        // Not currently stored in the Solr index
        return [];
    }

    /**
     * Get the UPC number(s) of the record.
     *
     * @return array
     */
    public function getUPC()
    {
        return isset($this->fields['upc_str_mv']) ?
            $this->fields['upc_str_mv'] : [];
    }

    /**
     * Return an array of associative URL arrays with one or more of the following
     * keys:
     *
     * <li>
     *   <ul>desc: URL description text to display (optional)</ul>
     *   <ul>url: fully-formed URL (required if 'route' is absent)</ul>
     *   <ul>route: VuFind route to build URL with (required if 'url' is absent)</ul>
     *   <ul>routeParams: Parameters for route (optional)</ul>
     *   <ul>queryString: Query params to append after building route (optional)</ul>
     * </li>
     *
     * @return array
     */
    public function getURLs()
    {
        // If non-empty, map internal URL array to expected return format;
        // otherwise, return empty array:
        if (isset($this->fields['url']) && is_array($this->fields['url'])) {
            $filter = function ($url) {
                return ['url' => $url];
            };
            return array_map($filter, $this->fields['url']);
        }
        return [];
    }

    /**
     * Get a hierarchy driver appropriate to the current object.  (May be false if
     * disabled/unavailable).
     *
     * @return \VuFind\Hierarchy\Driver\AbstractBase|bool
     */
    public function getHierarchyDriver()
    {
        if (null === $this->hierarchyDriver
            && null !== $this->hierarchyDriverManager
        ) {
            $type = $this->getHierarchyType();
            $this->hierarchyDriver = $type
                ? $this->hierarchyDriverManager->get($type) : false;
        }
        return $this->hierarchyDriver;
    }

    /**
     * Inject a hierarchy driver plugin manager.
     *
     * @param \VuFind\Hierarchy\Driver\PluginManager $pm Hierarchy driver manager
     *
     * @return SolrDefault
     */
    public function setHierarchyDriverManager(
        \VuFind\Hierarchy\Driver\PluginManager $pm
    ) {
        $this->hierarchyDriverManager = $pm;
        return $this;
    }

    /**
     * Get the hierarchy_top_id(s) associated with this item (empty if none).
     *
     * @return array
     */
    public function getHierarchyTopID()
    {
        return isset($this->fields['hierarchy_top_id'])
            ? $this->fields['hierarchy_top_id'] : [];
    }

    /**
     * Get the absolute parent title(s) associated with this item (empty if none).
     *
     * @return array
     */
    public function getHierarchyTopTitle()
    {
        return isset($this->fields['hierarchy_top_title'])
            ? $this->fields['hierarchy_top_title'] : [];
    }

    /**
     * Get an associative array (id => title) of collections containing this record.
     *
     * @return array
     */
    public function getContainingCollections()
    {
        // If collections are disabled or this record is not part of a hierarchy, go
        // no further....
        if (!isset($this->mainConfig->Collections->collections)
            || !$this->mainConfig->Collections->collections
            || !($hierarchyDriver = $this->getHierarchyDriver())
        ) {
            return false;
        }

        // Initialize some variables needed within the switch below:
        $isCollection = $this->isCollection();
        $titles = $ids = [];

        // Check config setting for what constitutes a collection, act accordingly:
        switch ($hierarchyDriver->getCollectionLinkType()) {
        case 'All':
            if (isset($this->fields['hierarchy_parent_title'])
                && isset($this->fields['hierarchy_parent_id'])
            ) {
                $titles = $this->fields['hierarchy_parent_title'];
                $ids = $this->fields['hierarchy_parent_id'];
            }
            break;
        case 'Top':
            if (isset($this->fields['hierarchy_top_title'])
                && isset($this->fields['hierarchy_top_id'])
            ) {
                foreach ($this->fields['hierarchy_top_id'] as $i => $topId) {
                    // Don't mark an item as its own parent -- filter out parent
                    // collections whose IDs match that of the current collection.
                    if (!$isCollection
                        || $topId !== $this->fields['is_hierarchy_id']
                    ) {
                        $ids[] = $topId;
                        $titles[] = $this->fields['hierarchy_top_title'][$i];
                    }
                }
            }
            break;
        }

        // Map the titles and IDs to a useful format:
        $c = count($ids);
        $retVal = [];
        for ($i = 0; $i < $c; $i++) {
            $retVal[$ids[$i]] = $titles[$i];
        }
        return $retVal;
    }

    /**
     * Get the value of whether or not this is a collection level record
     *
     * NOTE: \VuFind\Hierarchy\TreeDataFormatter\AbstractBase::isCollection()
     * duplicates some of this logic.
     *
     * @return bool
     */
    public function isCollection()
    {
        if (!($hierarchyDriver = $this->getHierarchyDriver())) {
            // Not a hierarchy type record
            return false;
        }

        // Check config setting for what constitutes a collection
        switch ($hierarchyDriver->getCollectionLinkType()) {
        case 'All':
            return (isset($this->fields['is_hierarchy_id']));
        case 'Top':
            return isset($this->fields['is_hierarchy_title'])
                && isset($this->fields['is_hierarchy_id'])
                && in_array(
                    $this->fields['is_hierarchy_id'],
                    $this->fields['hierarchy_top_id']
                );
        default:
            // Default to not be a collection level record
            return false;
        }
    }

    /**
     * Get a list of hierarchy trees containing this record.
     *
     * @param string $hierarchyID The hierarchy to get the tree for
     *
     * @return mixed An associative array of hierarchy trees on success
     * (id => title), false if no hierarchies found
     */
    public function getHierarchyTrees($hierarchyID = false)
    {
        $hierarchyDriver = $this->getHierarchyDriver();
        if ($hierarchyDriver && $hierarchyDriver->showTree()) {
            return $hierarchyDriver->getTreeRenderer($this)
                ->getTreeList($hierarchyID);
        }
        return false;
    }

    /**
     * Get the Hierarchy Type (false if none)
     *
     * @return string|bool
     */
    public function getHierarchyType()
    {
        if (isset($this->fields['hierarchy_top_id'])) {
            $hierarchyType = isset($this->fields['hierarchytype'])
                ? $this->fields['hierarchytype'] : false;
            if (!$hierarchyType) {
                $hierarchyType = isset($this->mainConfig->Hierarchy->driver)
                    ? $this->mainConfig->Hierarchy->driver : false;
            }
            return $hierarchyType;
        }
        return false;
    }

    /**
     * Return the unique identifier of this record within the Solr index;
     * useful for retrieving additional information (like tags and user
     * comments) from the external MySQL database.
     *
     * @return string Unique identifier.
     */
    public function getUniqueID()
    {
        if (!isset($this->fields['id'])) {
            throw new \Exception('ID not set!');
        }
        return $this->fields['id'];
    }

    /**
     * Return an XML representation of the record using the specified format.
     * Return false if the format is unsupported.
     *
     * @param string     $format     Name of format to use (corresponds with OAI-PMH
     * metadataPrefix parameter).
     * @param string     $baseUrl    Base URL of host containing VuFind (optional;
     * may be used to inject record URLs into XML when appropriate).
     * @param RecordLink $recordLink Record link helper (optional; may be used to
     * inject record URLs into XML when appropriate).
     *
     * @return mixed         XML, or false if format unsupported.
     */
    public function getXML($format, $baseUrl = null, $recordLink = null)
    {
        // For OAI-PMH Dublin Core, produce the necessary XML:
        if ($format == 'oai_dc') {
            $dc = 'http://purl.org/dc/elements/1.1/';
            $xml = new \SimpleXMLElement(
                '<oai_dc:dc '
                . 'xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/" '
                . 'xmlns:dc="' . $dc . '" '
                . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
                . 'xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/ '
                . 'http://www.openarchives.org/OAI/2.0/oai_dc.xsd" />'
            );
            $xml->addChild('title', htmlspecialchars($this->getTitle()), $dc);
            $authors = $this->getDeduplicatedAuthors();
            foreach ($authors as $list) {
                foreach ((array)$list as $author) {
                    $xml->addChild('creator', htmlspecialchars($author), $dc);
                }
            }
            foreach ($this->getLanguages() as $lang) {
                $xml->addChild('language', htmlspecialchars($lang), $dc);
            }
            foreach ($this->getPublishers() as $pub) {
                $xml->addChild('publisher', htmlspecialchars($pub), $dc);
            }
            foreach ($this->getPublicationDates() as $date) {
                $xml->addChild('date', htmlspecialchars($date), $dc);
            }
            foreach ($this->getAllSubjectHeadings() as $subj) {
                $xml->addChild(
                    'subject', htmlspecialchars(implode(' -- ', $subj)), $dc
                );
            }
            if (null !== $baseUrl && null !== $recordLink) {
                $url = $baseUrl . $recordLink->getUrl($this);
                $xml->addChild('identifier', $url, $dc);
            }

            return $xml->asXml();
        }

        // Unsupported format:
        return false;
    }

    /**
     * Get an array of strings representing citation formats supported
     * by this record's data (empty if none).  For possible legal values,
     * see /application/themes/root/helpers/Citation.php, getCitation()
     * method.
     *
     * @return array Strings representing citation formats.
     */
    protected function getSupportedCitationFormats()
    {
        return ['APA', 'Chicago', 'MLA'];
    }

    /**
     * Get the title of the item that contains this record (i.e. MARC 773s of a
     * journal).
     *
     * @return string
     */
    public function getContainerTitle()
    {
        return isset($this->fields['container_title'])
            ? $this->fields['container_title'] : '';
    }

    /**
     * Get the volume of the item that contains this record (i.e. MARC 773v of a
     * journal).
     *
     * @return string
     */
    public function getContainerVolume()
    {
        return isset($this->fields['container_volume'])
            ? $this->fields['container_volume'] : '';
    }

    /**
     * Get the issue of the item that contains this record (i.e. MARC 773l of a
     * journal).
     *
     * @return string
     */
    public function getContainerIssue()
    {
        return isset($this->fields['container_issue'])
            ? $this->fields['container_issue'] : '';
    }

    /**
     * Get the start page of the item that contains this record (i.e. MARC 773q of a
     * journal).
     *
     * @return string
     */
    public function getContainerStartPage()
    {
        return isset($this->fields['container_start_page'])
            ? $this->fields['container_start_page'] : '';
    }

    /**
     * Get the end page of the item that contains this record.
     *
     * @return string
     */
    public function getContainerEndPage()
    {
        // not currently supported by Solr index:
        return '';
    }

    /**
     * Get a full, free-form reference to the context of the item that contains this
     * record (i.e. volume, year, issue, pages).
     *
     * @return string
     */
    public function getContainerReference()
    {
        return isset($this->fields['container_reference'])
            ? $this->fields['container_reference'] : '';
    }

    /**
     * Get a sortable title for the record (i.e. no leading articles).
     *
     * @return string
     */
    public function getSortTitle()
    {
        return isset($this->fields['title_sort'])
            ? $this->fields['title_sort'] : parent::getSortTitle();
    }

    /**
     * Get longitude/latitude values (or empty array if not available).
     *
     * @return array
     */
    public function getLongLat()
    {
        return isset($this->fields['long_lat'])
            ? $this->fields['long_lat'] : [];
    }

    /**
     * Get schema.org type mapping, an array of sub-types of
     * http://schema.org/CreativeWork, defaulting to CreativeWork
     * itself if nothing else matches.
     *
     * @return array
     */
    public function getSchemaOrgFormatsArray()
    {
        $types = [];
        foreach ($this->getFormats() as $format) {
            switch ($format) {
            case 'Book':
            case 'eBook':
                $types['Book'] = 1;
                break;
            case 'Video':
            case 'VHS':
                $types['Movie'] = 1;
                break;
            case 'Photo':
                $types['Photograph'] = 1;
                break;
            case 'Map':
                $types['Map'] = 1;
                break;
            case 'Audio':
                $types['MusicAlbum'] = 1;
                break;
            default:
                $types['CreativeWork'] = 1;
            }
        }
        return array_keys($types);
    }

    /**
     * Get schema.org type mapping, expected to be a space-delimited string of
     * sub-types of http://schema.org/CreativeWork, defaulting to CreativeWork
     * itself if nothing else matches.
     *
     * @return string
     */
    public function getSchemaOrgFormats()
    {
        return implode(' ', $this->getSchemaOrgFormatsArray());
    }

    /**
     * Get information on records deduplicated with this one
     *
     * @return array Array keyed by source id containing record id
     */
    public function getDedupData()
    {
        return isset($this->fields['dedup_data'])
            ? $this->fields['dedup_data']
            : [];
    }

    /**
     * Attach a Search Results Plugin Manager connection and related logic to
     * the driver
     *
     * @param \VuFindSearch\Service $service Search Service Manager
     *
     * @return void
     */
    public function attachSearchService(\VuFindSearch\Service $service)
    {
        $this->searchService = $service;
    }

    /**
     * Get the number of child records belonging to this record
     *
     * @return int Number of records
     */
    public function getChildRecordCount()
    {
        // Shortcut: if this record is not the top record, let's not find out the
        // count. This assumes that contained records cannot contain more records.
        if (!$this->containerLinking
            || empty($this->fields['is_hierarchy_id'])
            || null === $this->searchService
        ) {
            return 0;
        }

        $safeId = addcslashes($this->fields['is_hierarchy_id'], '"');
        $query = new \VuFindSearch\Query\Query(
            'hierarchy_parent_id:"' . $safeId . '"'
        );
        return $this->searchService->search('Solr', $query, 0, 0)->getTotal();
    }

    /**
     * Get the container record id.
     *
     * @return string Container record id (empty string if none)
     */
    public function getContainerRecordID()
    {
        return $this->containerLinking
            && !empty($this->fields['hierarchy_parent_id'])
            ? $this->fields['hierarchy_parent_id'][0] : '';
    }

    /**
     * Get the bbox-geo variable.
     *
     * @return array
     */
    public function getGeoLocation()
    {
        return isset($this->fields['location_geo'])
            ? $this->fields['location_geo'] : [];
    }

    /**
     * Get the map display (lat/lon) coordinates
     *
     * @return array
     */
    public function getDisplayCoordinates()
    {
        return isset($this->fields['long_lat_display'])
            ? $this->fields['long_lat_display'] : [];
    }

    /**
     * Get the map display (lat/lon) labels
     *
     * @return array
     */
    public function getCoordinateLabels()
    {
        return isset($this->fields['long_lat_label'])
            ? $this->fields['long_lat_label'] : [];
    }
}
