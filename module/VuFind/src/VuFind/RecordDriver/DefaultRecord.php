<?php

/**
 * Default model for records
 *
 * PHP version 8
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

use VuFind\View\Helper\Root\RecordLinker;
use VuFindCode\ISBN;

use function count;
use function in_array;
use function is_array;
use function sprintf;
use function strlen;

/**
 * Default model for records
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 *
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 */
class DefaultRecord extends AbstractBase
{
    /**
     * Should we highlight fields in search results?
     *
     * @var bool
     */
    protected $highlight = false;

    /**
     * Constructor
     *
     * @param \Laminas\Config\Config $mainConfig     VuFind main configuration (omit
     * for built-in defaults)
     * @param \Laminas\Config\Config $recordConfig   Record-specific configuration
     * file (omit to use $mainConfig as $recordConfig)
     * @param \Laminas\Config\Config $searchSettings Search-specific configuration
     * file
     */
    public function __construct(
        $mainConfig = null,
        $recordConfig = null,
        $searchSettings = null
    ) {
        // Turn on highlighting as needed:
        $this->highlight = $searchSettings->General->highlighting ?? false;

        parent::__construct($mainConfig, $recordConfig);
    }

    /**
     * Get access restriction notes for the record.
     *
     * @return array
     */
    public function getAccessRestrictions()
    {
        // Not currently stored in the default index schema
        return [];
    }

    /**
     * Get all subject headings associated with this record. Each heading is
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
        $headings = [];
        foreach (['topic', 'geographic', 'genre', 'era'] as $field) {
            if (isset($this->fields[$field])) {
                $headings = array_merge($headings, $this->fields[$field]);
            }
        }

        // The default index schema doesn't currently store subject headings in a
        // broken-down format, so we'll just send each value as a single chunk.
        // Other record drivers (i.e. SolrMarc) can offer this data in a more
        // granular format.
        $callback = function ($i) use ($extended) {
            return $extended
                ? ['heading' => [$i], 'type' => '', 'source' => '']
                : [$i];
        };
        return array_map($callback, array_unique($headings));
    }

    /**
     * Get the subject headings as a flat array of strings.
     *
     * @return array Subject headings
     */
    public function getAllSubjectHeadingsFlattened()
    {
        $headings = [];
        $subjects = $this->getAllSubjectHeadings();
        if (is_array($subjects)) {
            foreach ($subjects as $subj) {
                $headings[] = implode(' -- ', $subj);
            }
        }
        return $headings;
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
     * Get Author Information with Associated Data Fields
     *
     * @param string $index      The author index [primary, corporate, or secondary]
     * used to construct a method name for retrieving author data (e.g.
     * getPrimaryAuthors).
     * @param array  $dataFields An array of fields to used to construct method
     * names for retrieving author-related data (e.g., if you pass 'role' the
     * data method will be similar to getPrimaryAuthorsRoles). This value will also
     * be used as a key associated with each author in the resulting data array.
     *
     * @return array
     */
    public function getAuthorDataFields($index, $dataFields = [])
    {
        $data = $dataFieldValues = [];

        // Collect author data
        $authorMethod = sprintf('get%sAuthors', ucfirst($index));
        $authors = $this->tryMethod($authorMethod, [], []);

        // Collect attribute data
        foreach ($dataFields as $field) {
            $fieldMethod = $authorMethod . ucfirst($field) . 's';
            $dataFieldValues[$field] = $this->tryMethod($fieldMethod, [], []);
        }

        // Match up author and attribute data (this assumes that the attribute
        // arrays have the same indices as the author array; i.e. $author[$i]
        // has $dataFieldValues[$attribute][$i].
        foreach ($authors as $i => $author) {
            if (!isset($data[$author])) {
                $data[$author] = [];
            }

            foreach ($dataFieldValues as $field => $dataFieldValue) {
                if (!empty($dataFieldValue[$i])) {
                    $data[$author][$field][] = $dataFieldValue[$i];
                }
            }
        }

        return $data;
    }

    /**
     * Get award notes for the record.
     *
     * @return array
     */
    public function getAwards()
    {
        // Not currently stored in the default index schema
        return [];
    }

    /**
     * Get notes on bibliography content.
     *
     * @return array
     */
    public function getBibliographyNotes()
    {
        // Not currently stored in the default index schema
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
        return $all[0] ?? '';
    }

    /**
     * Get all call numbers associated with the record.
     *
     * @return array
     */
    public function getCallNumbers()
    {
        return array_unique((array)($this->fields['callnumber-raw'] ?? []));
    }

    /**
     * Return the first valid DOI found in the record (false if none).
     *
     * @return mixed
     */
    public function getCleanDOI()
    {
        $field = 'doi_str_mv';
        return !empty($this->fields[$field][0]) ? $this->fields[$field][0] : false;
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
     * Return all ISBNs found in the record.
     *
     * @param string $mode          Mode for returning ISBNs:
     *  - 'only10' returns only ISBN-10s
     *  - 'prefer10' returns ISBN-10s if available, otherwise ISBN-13s (default)
     *  - 'normalize13' returns ISBN-13s, normalizing ISBN-10s to ISBN-13s
     * @param bool   $filterInvalid Whether to filter out invalid ISBNs
     *
     * @return array
     */
    public function getCleanISBNs(
        string $mode = 'prefer10',
        bool $filterInvalid = true
    ): array {
        $isbns = $this->getISBNs();
        $all = $tens = $thirteens = $invalid = [];
        foreach ($isbns as $isbn) {
            // Strip off any unwanted notes:
            if ($pos = strpos($isbn, ' ')) {
                $isbn = substr($isbn, 0, $pos);
            }
            $isbnObj = new ISBN($isbn);
            if ($isbnObj->isValid()) {
                if ($isbn10 = $isbnObj->get10()) {
                    $normalized
                        = $mode === 'normalize13' ? $isbnObj->get13() : $isbn10;
                    $tens[] = $normalized;
                    $all[] = $normalized;
                } elseif ($isbn13 = $isbnObj->get13()) {
                    $thirteens[] = $isbn13;
                    $all[] = $isbn13;
                }
            } elseif (!$filterInvalid) {
                $invalid[] = $isbn;
                $all[] = $isbn;
            }
        }
        if ($mode === 'only10') {
            return array_merge($tens, $invalid);
        }
        return $mode === 'prefer10'
            ? array_merge($tens, $thirteens, $invalid) : $all;
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
     * Get just the first listed national bibliography number (or false if none
     * available).
     *
     * @return mixed
     */
    public function getCleanNBN()
    {
        return false;
    }

    /**
     * Get just the base portion of the first listed ISMN (or false if no ISSMs).
     *
     * @return mixed
     */
    public function getCleanISMN()
    {
        return false;
    }

    /**
     * Get the main corporate authors (if any) for the record.
     *
     * @return array
     */
    public function getCorporateAuthors()
    {
        return (array)($this->fields['author_corporate'] ?? []);
    }

    /**
     * Get an array of all main corporate authors roles.
     *
     * @return array
     */
    public function getCorporateAuthorsRoles()
    {
        return (array)($this->fields['author_corporate_role'] ?? []);
    }

    /**
     * Get the date coverage for a record which spans a period of time (i.e. a
     * journal). Use getPublicationDates for publication dates of particular
     * monographic items.
     *
     * @return array
     */
    public function getDateSpan()
    {
        return (array)($this->fields['dateSpan'] ?? []);
    }

    /**
     * Deduplicate author information into associative array with main/corporate/
     * secondary keys.
     *
     * @param array $dataFields An array of extra data fields to retrieve (see
     * getAuthorDataFields)
     *
     * @return array
     */
    public function getDeduplicatedAuthors($dataFields = ['role'])
    {
        $authors = [];
        foreach (['primary', 'secondary', 'corporate'] as $type) {
            $authors[$type] = $this->getAuthorDataFields($type, $dataFields);
        }

        // deduplicate
        $dedup = function (&$array1, &$array2) {
            if (!empty($array1) && !empty($array2)) {
                $keys = array_keys($array1);
                foreach ($keys as $author) {
                    if (isset($array2[$author])) {
                        $array1[$author] = array_merge_recursive(
                            $array1[$author],
                            $array2[$author]
                        );
                        unset($array2[$author]);
                    }
                }
            }
        };

        $dedup($authors['primary'], $authors['corporate']);
        $dedup($authors['secondary'], $authors['corporate']);
        $dedup($authors['primary'], $authors['secondary']);

        $dedup_data = function (&$array) {
            foreach ($array as $author => $data) {
                foreach ($data as $field => $values) {
                    if (is_array($values)) {
                        $array[$author][$field] = array_unique($values);
                    }
                }
            }
        };

        $dedup_data($authors['primary']);
        $dedup_data($authors['secondary']);
        $dedup_data($authors['corporate']);

        return $authors;
    }

    /**
     * Get the edition of the current record.
     *
     * @return string
     */
    public function getEdition()
    {
        return $this->fields['edition'] ?? '';
    }

    /**
     * Get notes on finding aids related to the record.
     *
     * @return array
     */
    public function getFindingAids()
    {
        // Not currently stored in the default index schema
        return [];
    }

    /**
     * Get an array of all the formats associated with the record.
     *
     * @return array
     */
    public function getFormats()
    {
        return (array)($this->fields['format'] ?? []);
    }

    /**
     * Get general notes on the record.
     *
     * @return array
     */
    public function getGeneralNotes()
    {
        // Not currently stored in the default index schema
        return [];
    }

    /**
     * Get highlighted author data, if available.
     *
     * @return array
     */
    public function getRawAuthorHighlights()
    {
        // Not supported by default.
        return [];
    }

    /**
     * Get primary author information with highlights applied (if applicable)
     *
     * @return array
     */
    public function getPrimaryAuthorsWithHighlighting()
    {
        $highlights = [];
        // Create a map of de-highlighted values => highlighted values.
        foreach ($this->getRawAuthorHighlights() as $current) {
            $dehighlighted = str_replace(
                ['{{{{START_HILITE}}}}', '{{{{END_HILITE}}}}'],
                '',
                $current
            );
            $highlights[$dehighlighted] = $current;
        }

        // replace unhighlighted authors with highlighted versions where
        // applicable:
        $authors = [];
        foreach ($this->getPrimaryAuthors() as $author) {
            $authors[] = $highlights[$author] ?? $author;
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
        return $this->fields['last_indexed'] ?? '';
    }

    /**
     * Given a field name, return an appropriate caption.
     *
     * @param string $field Field name
     *
     * @return mixed        Caption if found, false if none available.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getSnippetCaption($field)
    {
        // Not supported by default.
        return false;
    }

    /**
     * Pick one line from the highlighted text (if any) to use as a snippet.
     *
     * @return mixed False if no snippet found, otherwise associative array
     * with 'snippet' and 'caption' keys.
     */
    public function getHighlightedSnippet()
    {
        // Not supported by default.
        return false;
    }

    /**
     * Get a highlighted title string, if available.
     *
     * @return string
     */
    public function getHighlightedTitle()
    {
        // Not supported by default.
        return '';
    }

    /**
     * Get the institutions holding the record.
     *
     * @return array
     */
    public function getInstitutions()
    {
        return (array)($this->fields['institution'] ?? []);
    }

    /**
     * Get the buildings containing the record.
     *
     * @return array
     */
    public function getBuildings()
    {
        return (array)($this->fields['building'] ?? []);
    }

    /**
     * Get an array of all ISBNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISBNs()
    {
        return (array)($this->fields['isbn'] ?? []);
    }

    /**
     * Get an array of all ISSNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISSNs()
    {
        return (array)($this->fields['issn'] ?? []);
    }

    /**
     * Get an array of all the languages associated with the record.
     *
     * @return array
     */
    public function getLanguages()
    {
        return (array)($this->fields['language'] ?? []);
    }

    /**
     * Get a raw, unnormalized LCCN. (See getLCCN for normalization).
     *
     * @return string
     */
    protected function getRawLCCN()
    {
        // Get LCCN from Index
        return $this->fields['lccn'] ?? '';
    }

    /**
     * Get a LCCN, normalised according to info:lccn
     *
     * @return string
     */
    public function getLCCN()
    {
        // Remove all blanks.
        $raw = preg_replace('{[ \t]+}', '', $this->getRawLCCN());

        // If there is a forward slash (/) in the string, remove it, and remove all
        // characters to the right of the forward slash.
        if (strpos($raw, '/') > 0) {
            $tmpArray = explode('/', $raw);
            $raw = $tmpArray[0];
        }
        /* If there is a hyphen in the string:
            a. Remove it.
            b. Inspect the substring following (to the right of) the (removed)
               hyphen. Then (and assuming that steps 1 and 2 have been carried out):
                    i. All these characters should be digits, and there should be
                    six or less.
                    ii. If the length of the substring is less than 6, left-fill the
                    substring with zeros until  the length is six.
        */
        if (strpos($raw, '-') > 0) {
            // haven't checked for i. above. If they aren't all digits, there is
            // nothing that can be done, so might as well leave it.
            $tmpArray = explode('-', $raw);
            $raw = $tmpArray[0] . str_pad($tmpArray[1], 6, '0', STR_PAD_LEFT);
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
        return (array)($this->fields['title_new'] ?? []);
    }

    /**
     * Get the OCLC number(s) of the record.
     *
     * @return array
     */
    public function getOCLC()
    {
        return (array)($this->fields['oclc_num'] ?? []);
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
        if (in_array('Book', $formats) || in_array('eBook', $formats)) {
            return 'Book';
        } elseif (in_array('Article', $formats)) {
            return 'Article';
        } elseif (in_array('Journal', $formats)) {
            return 'Journal';
        } elseif (strlen($this->getCleanISSN()) > 0) {
            // If the record has an ISSN and we have not already
            // decided it is an Article, we'll treat it as a Book
            // if it has an ISBN and is therefore likely part of a
            // monographic series. Otherwise, we'll treat it as a
            // Journal.
            // Anecdotally, some link resolvers do not return correct
            // results when given both ISBN and ISSN for a member of a
            // monographic series.
            return strlen($this->getCleanISBN()) > 0 ? 'Book' : 'Journal';
        } elseif (isset($formats[0])) {
            return $formats[0];
        } elseif (strlen($this->getCleanISBN()) > 0) {
            // Last ditch. Note that this is last by intention; if the
            // record has a format set and also has an ISBN, we don't
            // necessarily want to send the ISBN, as it may be a game
            // or a DVD that wouldn't typically be found in OpenURL
            // knowledgebases.
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
        if (
            isset($this->mainConfig->OpenURL->rfr_id)
            && !empty($this->mainConfig->OpenURL->rfr_id)
        ) {
            return $this->mainConfig->OpenURL->rfr_id;
        }
        if (
            isset($this->mainConfig->COinS->identifier)
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
            'rft.date' => $pubDate,
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
        $placesOfPublication = $this->getPlacesOfPublication();
        if (count($placesOfPublication) > 0) {
            $params['rft.place'] = $placesOfPublication[0];
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

        // If we're working with the SFX or Alma resolver, we should add a
        // special parameter to ensure that electronic holdings links
        // are shown even though no specific date or issue is specified:
        $resolver = strtolower($this->mainConfig->OpenURL->resolver ?? '');
        if ('sfx' === $resolver) {
            $params['sfx.ignore_date_threshold'] = 1;
        } elseif ('alma' === $resolver) {
            $params['u.ignore_date_coverage'] = 'true';
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
        $query = [];
        foreach ($params as $key => $value) {
            $value = (array)$value;
            foreach ($value as $sub) {
                $query[] = urlencode($key) . '=' . urlencode($sub);
            }
        }
        return implode('&', $query);
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
        return (array)($this->fields['physical'] ?? []);
    }

    /**
     * Get the item's place of publication.
     *
     * @return array
     */
    public function getPlacesOfPublication()
    {
        // Not currently stored in the default index schema
        return [];
    }

    /**
     * Get an array of playing times for the record (if applicable).
     *
     * @return array
     */
    public function getPlayingTimes()
    {
        // Not currently stored in the default index schema
        return [];
    }

    /**
     * Get an array of previous titles for the record.
     *
     * @return array
     */
    public function getPreviousTitles()
    {
        return (array)($this->fields['title_old'] ?? []);
    }

    /**
     * Get the main author of the record.
     *
     * @return string
     */
    public function getPrimaryAuthor()
    {
        $authors = $this->getPrimaryAuthors();
        return $authors[0] ?? '';
    }

    /**
     * Get the main authors of the record.
     *
     * @return array
     */
    public function getPrimaryAuthors()
    {
        return (array)($this->fields['author'] ?? []);
    }

    /**
     * Get an array of all main authors roles (complementing
     * getSecondaryAuthorsRoles()).
     *
     * @return array
     */
    public function getPrimaryAuthorsRoles()
    {
        return (array)($this->fields['author_role'] ?? []);
    }

    /**
     * Get credits of people involved in production of the item.
     *
     * @return array
     */
    public function getProductionCredits()
    {
        // Not currently stored in the default index schema
        return [];
    }

    /**
     * Get the publication dates of the record.  See also getDateSpan().
     *
     * @return array
     */
    public function getPublicationDates()
    {
        return (array)($this->fields['publishDate'] ?? []);
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
                $places[$i] ?? '',
                $names[$i] ?? '',
                $dates[$i] ?? ''
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
        // Not currently stored in the default index schema
        return [];
    }

    /**
     * Get the publishers of the record.
     *
     * @return array
     */
    public function getPublishers()
    {
        return (array)($this->fields['publisher'] ?? []);
    }

    /**
     * Get an array of information about record history, obtained in real-time
     * from the ILS.
     *
     * @return array
     */
    public function getRealTimeHistory()
    {
        // Not supported by the default index schema -- implement in child classes.
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
        // Not supported by the default index schema -- implement in child classes.
        return ['holdings' => []];
    }

    /**
     * Get an array of strings describing relationships to other items.
     *
     * @return array
     */
    public function getRelationshipNotes()
    {
        // Not currently stored in the default index schema
        return [];
    }

    /**
     * Get an array of all secondary authors (complementing getPrimaryAuthors()).
     *
     * @return array
     */
    public function getSecondaryAuthors()
    {
        return (array)($this->fields['author2'] ?? []);
    }

    /**
     * Get an array of all secondary authors roles (complementing
     * getPrimaryAuthorsRoles()).
     *
     * @return array
     */
    public function getSecondaryAuthorsRoles()
    {
        return (array)($this->fields['author2_role'] ?? []);
    }

    /**
     * Get an array of all series names containing the record. Array entries may
     * be either the name string, or an associative array with 'name' and 'number'
     * keys.
     *
     * @return array
     */
    public function getSeries()
    {
        // Only use the contents of the series2 field if the series field is empty
        return !empty($this->fields['series'])
            ? (array)$this->fields['series']
            : (array)($this->fields['series2'] ?? []);
    }

    /**
     * Get the short (pre-subtitle) title of the record.
     *
     * @return string
     */
    public function getShortTitle()
    {
        return $this->fields['title_short'] ?? '';
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
        return $this->fields['title_sub'] ?? '';
    }

    /**
     * Get an array of technical details on the item represented by the record.
     *
     * @return array
     */
    public function getSystemDetails()
    {
        // Not currently stored in the default index schema
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
        // array (it should be a flat string according to the default schema, but we
        // might as well support the array case just to be on the safe side:
        return (array)($this->fields['description'] ?? []);
    }

    /**
     * Get an array of note about the record's target audience.
     *
     * @return array
     */
    public function getTargetAudienceNotes()
    {
        // Not currently stored in the default index schema
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
        if (!empty($this->fields['thumbnail'])) {
            return $this->fields['thumbnail'];
        }
        $arr = [
            'author'     => mb_substr($this->getPrimaryAuthor(), 0, 300, 'utf-8'),
            'callnumber' => $this->getCallNumber(),
            'size'       => $size,
            'title'      => mb_substr($this->getTitle(), 0, 300, 'utf-8'),
            'recordid'   => $this->getUniqueID(),
            'source'   => $this->getSourceIdentifier(),
        ];
        $isbns = $this->getCleanISBNs();
        if (!empty($isbns)) {
            $arr['isbns'] = $isbns;
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
        if ($nbn = $this->getCleanNBN()) {
            $arr['nbn'] = $nbn['nbn'];
        }
        if ($ismn = $this->getCleanISMN()) {
            $arr['ismn'] = $ismn;
        }
        if ($uuid = $this->getCleanUuid()) {
            $arr['uuid'] = $uuid;
        }

        // If an ILS driver has injected extra details, check for IDs in there
        // to fill gaps:
        if ($ilsDetails = $this->getExtraDetail('ils_details')) {
            $idTypes = ['isbn', 'issn', 'oclc', 'upc', 'nbn', 'ismn', 'uuid'];
            foreach ($idTypes as $key) {
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
        return $this->fields['title'] ?? '';
    }

    /**
     * Get the text of the part/section portion of the title.
     *
     * @return string
     */
    public function getTitleSection()
    {
        // Not currently stored in the default index schema
        return '';
    }

    /**
     * Get the statement of responsibility that goes with the title (i.e. "by John
     * Smith").
     *
     * @return string
     */
    public function getTitleStatement()
    {
        // Not currently stored in the default index schema
        return '';
    }

    /**
     * Get an array of lines from the table of contents.
     *
     * @return array
     */
    public function getTOC()
    {
        return (array)($this->fields['contents'] ?? []);
    }

    /**
     * Get hierarchical place names
     *
     * @return array
     */
    public function getHierarchicalPlaceNames()
    {
        // Not currently stored in the default index schema
        return [];
    }

    /**
     * Get the UPC number(s) of the record.
     *
     * @return array
     */
    public function getUPC()
    {
        return (array)($this->fields['upc_str_mv'] ?? []);
    }

    /**
     * Get UUIDs (Universally unique identifier). These are commonly used in, for
     * example, digital library or repository systems and can be a useful match
     * point with third party systems.
     *
     * @return array
     */
    public function getUuids()
    {
        return (array)($this->fields['uuid_str_mv'] ?? []);
    }

    /**
     * Get just the first listed UUID (Universally unique identifier), or false if
     * none available.
     *
     * @return mixed
     */
    public function getCleanUuid()
    {
        $uuids = $this->getUuids();
        return empty($uuids) ? false : $uuids[0];
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
        $filter = function ($url) {
            return ['url' => $url];
        };
        return array_map($filter, (array)($this->fields['url'] ?? []));
    }

    /**
     * Get the hierarchy_top_id(s) associated with this item (empty if none).
     *
     * @return array
     */
    public function getHierarchyTopID()
    {
        // Unsupported by default:
        return [];
    }

    /**
     * Get the absolute parent title(s) associated with this item (empty if none).
     *
     * @return array
     */
    public function getHierarchyTopTitle()
    {
        // Unsupported by default:
        return [];
    }

    /**
     * Get an associative array (id => title) of collections containing this record.
     *
     * @return array
     */
    public function getContainingCollections()
    {
        // Unsupported by default:
        return [];
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
        // Unsupported by default:
        return false;
    }

    /**
     * Get a list of hierarchy trees containing this record.
     *
     * @param string $hierarchyID The hierarchy to get the tree for
     *
     * @return mixed An associative array of hierarchy trees on success
     * (id => title), false if no hierarchies found
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHierarchyTrees($hierarchyID = false)
    {
        // Unsupported by default:
        return false;
    }

    /**
     * Get the Hierarchy Type (false if none)
     *
     * @return string|bool
     */
    public function getHierarchyType()
    {
        // Unsupported by default:
        return false;
    }

    /**
     * Return the unique identifier of this record within the index;
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
     * @param string       $format  Name of format to use (corresponds with
     * OAI-PMH metadataPrefix parameter).
     * @param string       $baseUrl Base URL of host containing VuFind (optional;
     * may be used to inject record URLs into XML when appropriate).
     * @param RecordLinker $linker  Record linker helper (optional; may be used to
     * inject record URLs into XML when appropriate).
     *
     * @return mixed XML, or false if format unsupported.
     */
    public function getXML($format, $baseUrl = null, $linker = null)
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
                foreach (array_keys($list) as $author) {
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
                    'subject',
                    htmlspecialchars(implode(' -- ', $subj)),
                    $dc
                );
            }
            if (null !== $baseUrl && null !== $linker) {
                $url = $baseUrl . $linker->getUrl($this);
                $xml->addChild('identifier', $url, $dc);
            }

            return $xml->asXml();
        }

        // Unsupported format:
        return false;
    }

    /**
     * Get an array of strings representing citation formats supported
     * by this record's data (empty if none). For possible legal values,
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
        return $this->fields['container_title'] ?? '';
    }

    /**
     * Get the volume of the item that contains this record (i.e. MARC 773v of a
     * journal).
     *
     * @return string
     */
    public function getContainerVolume()
    {
        return $this->fields['container_volume'] ?? '';
    }

    /**
     * Get the issue of the item that contains this record (i.e. MARC 773l of a
     * journal).
     *
     * @return string
     */
    public function getContainerIssue()
    {
        return $this->fields['container_issue'] ?? '';
    }

    /**
     * Get the start page of the item that contains this record (i.e. MARC 773q of a
     * journal).
     *
     * @return string
     */
    public function getContainerStartPage()
    {
        return $this->fields['container_start_page'] ?? '';
    }

    /**
     * Get the end page of the item that contains this record.
     *
     * @return string
     */
    public function getContainerEndPage()
    {
        // Not supported by the default index schema -- implement in child classes.
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
        return $this->fields['container_reference'] ?? '';
    }

    /**
     * Get a sortable title for the record (i.e. no leading articles).
     *
     * @return string
     */
    public function getSortTitle()
    {
        return $this->fields['title_sort'] ?? parent::getSortTitle();
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

        // Check for functionality from IlsAwareTrait. If this record comes from a real ILS, we need
        // to add a type of "Product" to support the system's use of https://schema.org/Offer to
        // represent availability.
        if ($this->tryMethod('hasILS') && isset($this->ils) && $this->ils->getOfflineMode() !== 'ils-none') {
            $types['Product'] = 1;
        }

        return array_keys($types);
    }

    /**
     * Get schema.org type mapping, expected to be a space-delimited string of
     * sub-types of http://schema.org/CreativeWork, defaulting to CreativeWork
     * itself if nothing else matches.
     *
     * @return string
     *
     * @deprecated Use \VuFind\View\Helper\Root\SchemaOrg::getRecordTypes()
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
        return $this->fields['dedup_data'] ?? [];
    }

    /**
     * Get the number of child records belonging to this record
     *
     * @return int Number of records
     */
    public function getChildRecordCount()
    {
        // Unsupported by default
        return 0;
    }

    /**
     * Get the container record id.
     *
     * @return string Container record id (empty string if none)
     */
    public function getContainerRecordID()
    {
        // Unsupported by default
        return '';
    }

    /**
     * Get the bbox-geo variable.
     *
     * @return array
     */
    public function getGeoLocation()
    {
        return (array)($this->fields['long_lat'] ?? []);
    }

    /**
     * Get the map display (lat/lon) coordinates
     *
     * @return array
     */
    public function getDisplayCoordinates()
    {
        return (array)($this->fields['long_lat_display'] ?? []);
    }

    /**
     * Get the map display (lat/lon) labels
     *
     * @return array
     */
    public function getCoordinateLabels()
    {
        return (array)($this->fields['long_lat_label'] ?? []);
    }
}
