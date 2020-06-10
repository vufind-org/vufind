<?php
/**
 * Additional functionality for Finna Solr records.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library 2015-2019.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Additional functionality for Finna Solr records.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 *
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 */
trait SolrFinnaTrait
{
    use SolrCommonFinnaTrait;

    /**
     * Search settings
     *
     * @var array
     */
    protected $searchSettings = [];

    /**
     * Return access restriction notes for the record.
     *
     * @return array
     */
    public function getAccessRestrictions()
    {
        // Not currently stored in the Solr index
        return [];
    }

    /**
     * Return type of access restriction for the record.
     *
     * @return mixed array with keys:
     *   'copyright'   Copyright (e.g. 'CC BY 4.0')
     *   'link'        Link to copyright info, see IndexRecord::getRightsLink
     *   or false if no access restriction type is defined.
     */
    public function getAccessRestrictionsType()
    {
        return false;
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
                $data[$author][$field][] = !empty($dataFieldValue[$i])
                    ? $dataFieldValue[$i] : '-';
            }
        }

        return $data;
    }

    /**
     * Return all presenter and non-presenter authors as an array.
     *
     * @return array
     */
    public function getAuthorsWithRoles()
    {
        $nonPresenters = $this->getNonPresenterAuthors();
        $presenters = $this->getPresenters();
        return isset($presenters['presenters'])
            ? array_merge($nonPresenters, $presenters['presenters'])
            : $nonPresenters;
    }

    /**
     * Get record rating.
     *
     * @return array Keys 'average' and 'count'
     */
    public function getAverageRating()
    {
        $table = $this->getDbTable('Comments');
        return $table->getAverageRatingForResource(
            $this->getUniqueId(), $this->getSourceIdentifier()
        );
    }

    /**
     * Return building from index.
     *
     * @return array
     */
    public function getBuilding()
    {
        return isset($this->fields['building']) ? (array)$this->fields['building']
            : [];
    }

    /**
     * Return geographic center point
     *
     * @return array lon, lat
     */
    public function getGeoCenter()
    {
        if (isset($this->fields['center_coords'])) {
            if (strstr($this->fields['center_coords'], ',') !== false) {
                list($lat, $lon) = explode(',', $this->fields['center_coords'], 2);
            } else {
                list($lon, $lat) = explode(' ', $this->fields['center_coords'], 2);
            }
            return ['lon' => $lon, 'lat' => $lat];
        }
        return [];
    }

    /**
     * Get data source id
     *
     * @return string
     */
    public function getDataSource()
    {
        return isset($this->fields['datasource_str_mv'])
            ? ((array)$this->fields['datasource_str_mv'])[0]
            : '';
    }

    /**
     * Return an external URL where a displayable description text
     * can be retrieved from, if available; false otherwise.
     *
     * @return mixed
     */
    public function getDescriptionURL()
    {
        return false;
    }

    /**
     * Return education programs
     *
     * @return array
     */
    public function getEducationPrograms()
    {
        return [];
    }

    /**
     * Get the full title of the record.
     *
     * @return string
     */
    public function getFullTitle()
    {
        return isset($this->fields['title_full']) ?
            $this->fields['title_full'] : '';
    }

    /**
     * Return genres
     *
     * @return array
     */
    public function getGenres()
    {
        return isset($this->fields['genre'])
            ? $this->fields['genre'] : [];
    }

    /**
     * Return geographic locations (coordinates)
     *
     * @return array
     */
    public function getGeoLocations()
    {
        return isset($this->fields['location_geo'])
            ? $this->fields['location_geo'] : [];
    }

    /**
     * Get the hierarchy_parent_id(s) associated with this item (empty if none).
     *
     * @return array
     */
    public function getHierarchyParentID()
    {
        return isset($this->fields['hierarchy_parent_id'])
            ? $this->fields['hierarchy_parent_id'] : [];
    }

    /**
     * Get the parent title(s) associated with this item (empty if none).
     *
     * @return array
     */
    public function getHierarchyParentTitle()
    {
        return isset($this->fields['hierarchy_parent_title'])
            ? $this->fields['hierarchy_parent_title'] : [];
    }

    /**
     * Get identifier
     *
     * @return array
     */
    public function getIdentifier()
    {
        return [];
    }

    /**
     * Return image description.
     *
     * @param int $index Image index
     *
     * @return string
     */
    public function getImageDescription($index = 0)
    {
        $images = array_values($this->getAllImages());
        if (!empty($images[$index])) {
            return $images[$index]['description'];
        }
        return '';
    }

    /**
     * Return image rights.
     *
     * @param string $language Language
     *
     * @return mixed array with keys:
     *   'copyright'  Copyright (e.g. 'CC BY 4.0') (optional)
     *   'description Human readable description (array)
     *   'link'       Link to copyright info
     *   or false if the record contains no images
     */
    public function getImageRights($language)
    {
        return false;
    }

    /**
     * Return keywords
     *
     * @return array
     */
    public function getKeywords()
    {
        return [];
    }

    /**
     * Return local record IDs (only works with dedup records)
     *
     * @return array
     */
    public function getLocalIds()
    {
        return isset($this->fields['local_ids_str_mv'])
            ? $this->fields['local_ids_str_mv'] : [];
    }

    /**
     * Get an array of dedup and link data associated with the record.
     *
     * @return array
     */
    public function getMergedRecordData()
    {
        if (empty($this->searchSettings['Records']['deduplication'])) {
            // Do nothing if deduplication isn't enabled
            return [];
        }

        // If local_ids_str_mv is set, we already have all
        if (isset($this->fields['local_ids_str_mv'])) {
            return [
                'records' => $this->createSourceIdArray(
                    $this->fields['local_ids_str_mv']
                ),
                'urls' => isset($this->fields['online_urls_str_mv'])
                    ? $this->mergeURLArray(
                        $this->fields['online_urls_str_mv'],
                        true
                    ) : []
            ];
        }

        // Check for cached data
        if (isset($this->cachedMergeRecordData)) {
            return $this->cachedMergeRecordData;
        }

        // Check if this is a merged record
        if (empty($this->fields['merged_child_boolean'])) {
            return [];
        }

        // Find the dedup record
        if (null === $this->searchService) {
            return [];
        }

        if (!empty($this->fields['dedup_id_str_mv'])) {
            $records = $this->searchService->retrieve(
                $this->getSourceIdentifier(), $this->fields['dedup_id_str_mv'][0]
            )->getRecords();
        } else {
            $safeId = addcslashes($this->getUniqueID(), '"');
            $query = new \VuFindSearch\Query\Query(
                'local_ids_str_mv:"' . $safeId . '"'
            );
            $params = new \VuFindSearch\ParamBag(
                ['hl' => 'false', 'spellcheck' => 'false', 'sort' => '']
            );
            $records = $this->searchService->search(
                $this->getSourceIdentifier(), $query, 0, 1, $params
            )->getRecords();
        }
        if (!isset($records[0])) {
            $this->cachedMergeRecordData = [];
            return [];
        }
        $dedupRecord = $records[0];

        $results = [];
        $results['records'] = $this->createSourceIdArray(
            $dedupRecord->getLocalIds()
        );
        if ($onlineURLs = $dedupRecord->getOnlineURLs(true)) {
            $results['urls'] = $this->mergeURLArray(
                $onlineURLs,
                true
            );
        }
        $this->cachedMergeRecordData = $results;
        return $results;
    }

    /**
     * Get all authors apart from presenters
     *
     * @return array
     */
    public function getNonPresenterAuthors()
    {
        $authors = [];
        foreach ($this->getPrimaryAuthors() as $author) {
            $authors[] = ['name' => $author];
        }
        foreach ($this->getCorporateAuthors() as $author) {
            $authors[] = ['name' => $author];
        }
        foreach ($this->getSecondaryAuthors() as $author) {
            $authors[] = ['name' => $author];
        }
        return $authors;
    }

    /**
     * Get online URLs
     *
     * @param bool $raw Whether to return raw data
     *
     * @return array
     */
    public function getOnlineURLs($raw = false)
    {
        if (!isset($this->fields['online_urls_str_mv'])) {
            return [];
        }
        return $raw ? $this->fields['online_urls_str_mv'] : $this->checkForAudioUrls(
            $this->mergeURLArray(
                $this->fields['online_urls_str_mv'], true
            )
        );
    }

    /**
     * Get organisation info ID (Kirjastohakemisto Finna ID).
     *
     * @return string
     */
    public function getOrganisationInfoId()
    {
        $building = $this->getBuilding();
        if (empty($building)) {
            return null;
        }

        if (is_array($building)) {
            $building = $building[0];
        }

        if (preg_match('/^0\/([^\/]*)\/$/', $building, $matches)) {
            // strip leading '0/' and trailing '/' from top-level building code
            return $matches[1];
        }
        return null;
    }

    /**
     * Get all the original languages associated with the record
     *
     * @return array
     */
    public function getOriginalLanguages()
    {
        return isset($this->fields['original_lng_str_mv'])
            ? $this->fields['original_lng_str_mv'] : [];
    }

    /**
     * Get presenters
     *
     * @return array
     */
    public function getPresenters()
    {
        return [];
    }

    /**
     * Return record format.
     *
     * @return string
     */
    public function getRecordType()
    {
        return $this->fields['recordtype'] ?? '';
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
        $result = parent::getThumbnail($size);

        if (is_array($result) && !isset($result['isbn'])) {
            // Allow also invalid ISBNs
            if ($isbn = $this->getFirstISBN()) {
                $result['invisbn'] = $isbn;
            }
        }

        return $result;
    }

    /**
     * Get usage rights (empty if none).
     *
     * @return array
     */
    public function getUsageRights()
    {
        return isset($this->fields['usage_rights_str_mv'])
            ? $this->fields['usage_rights_str_mv'] : [];
    }

    /**
     * Return the first ISBN found in the record.
     *
     * @return mixed
     */
    public function getFirstISBN()
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

            $isbn = \VuFindCode\ISBN::normalizeISBN($isbn);
            $length = strlen($isbn);
            if ($length == 10 || $length == 13) {
                return $isbn;
            }
        }
        return $isbn13;
    }

    /**
     * Return SFX Object ID
     *
     * @return string
     */
    public function getSfxObjectId()
    {
        return '';
    }

    /**
     * Return Alma MMS ID
     *
     * @return string
     */
    public function getAlmaMmsId()
    {
        return '';
    }

    /**
     * Return record source.
     *
     * @return string
     */
    public function getSource()
    {
        return isset($this->fields['source_str_mv'])
            ? $this->fields['source_str_mv'] : false;
    }

    /**
     * Return main year.
     *
     * @return string|false
     */
    public function getYear()
    {
        return isset($this->fields['main_date_str'])
            ? $this->fields['main_date_str'] : false;
    }

    /**
     * Get a string representing the first date that the record was indexed.
     *
     * @return string
     */
    public function getFirstIndexed()
    {
        return isset($this->fields['first_indexed'])
            ? $this->fields['first_indexed'] : '';
    }

    /**
     * Is rating allowed.
     *
     * @return boolean
     */
    public function ratingAllowed()
    {
        $allowed = ['0/Book/', '0/Journal/', '0/Sound/', '0/Video/'];
        $list = array_intersect($allowed, $this->getFormats());
        return !empty($list);
    }

    /**
     * Is social media sharing allowed
     *
     * @return boolean
     */
    public function socialMediaSharingAllowed()
    {
        return true;
    }

    /**
     * Returns true if the record supports real-time AJAX status lookups.
     *
     * @return bool
     */
    public function supportsAjaxStatus()
    {
        if (parent::supportsAjaxStatus()) {
            if ($this->ils) {
                $driver = $this->ils->getDriver(false);
                if ($driver instanceof \VuFind\ILS\Driver\MultiBackend) {
                    $driverConfig = $this->ils->getDriverConfig();
                    list($source) = explode('.', $this->getUniqueID());
                    return isset($driverConfig['Drivers'][$source]);
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Checks the current record if it's supported for generating OpenURLs.
     *
     * @return bool
     */
    public function supportsOpenUrl()
    {
        // OpenURL is supported only if we have an ISSN, ISBN or SFX Object ID,
        // or Alma MMS ID .
        $formats = $this->getFormats();
        $isDatabase = '0/Database/' === (string)($formats[0] ?? '');
        return $this->getCleanISSN() || $this->getCleanISBN()
            || $this->getSfxObjectId()
            || ($this->getAlmaMmsId() && !$isDatabase);
    }

    /**
     * Is this an authority index record?
     *
     * @return bool
     */
    public function isAuthorityRecord()
    {
        return false;
    }

    /**
     * Support method for getOpenURL() -- pick the OpenURL format.
     *
     * @return string
     */
    protected function getOpenUrlFormat()
    {
        // If we have multiple formats, Book, Journal and Article are most
        // important...
        $formats = $this->getFormats();
        if (in_array('1/Book/BookSection/', $formats)
            || in_array('1/Book/eBookSection/', $formats)
        ) {
            return 'BookSection';
        } elseif (in_array('0/Book/', $formats)) {
            return 'Book';
        } elseif (in_array('1/Journal/Article/', $formats)
            || in_array('1/Journal/eArticle/', $formats)
        ) {
            return 'Article';
        } elseif (in_array('0/Journal/', $formats)
            || in_array('1/Other/ContinuouslyUpdatedResource/', $formats)
        ) {
            return 'Journal';
        } elseif (strlen($this->getCleanISSN()) > 0) {
            return 'Journal';
        } elseif (strlen($this->getCleanISBN()) > 0) {
            return 'Book';
        } elseif (isset($formats[0])) {
            $format = explode('/', $formats[0]);
            if (isset($format[1])) {
                return $format[1];
            }
            if ($formats[0] instanceof \VuFind\I18n\TranslatableStringInterface) {
                return $formats[0]->getDisplayString();
            }
        } elseif (strlen($this->getCleanISSN()) > 0) {
            return 'Journal';
        }
        return 'Book';
    }

    /**
     * Extract sources from record IDs and create an array of sources and IDs
     *
     * @param array $ids Record ID's
     *
     * @return array Formatted array
     */
    protected function createSourceIdArray($ids)
    {
        $results = [];
        $sourceFilter = !empty($this->searchSettings['Records']['sources'])
            ? explode(',', $this->searchSettings['Records']['sources']) : [];
        foreach ($ids as $id) {
            list($source) = explode('.', $id);
            if ($sourceFilter && !in_array($source, $sourceFilter)) {
                continue;
            }
            $results[] = [
                'source' => $source,
                'id' => $id
            ];
        }
        if (!empty($this->recordConfig->Record->sort_sources)) {
            usort(
                $results,
                function ($a, $b) {
                    return strcasecmp(
                        $this->translate('source_' . $a['source']),
                        $this->translate('source_' . $b['source'])
                    );
                }
            );
        }
        return $results;
    }

    /**
     * Get information on records deduplicated with this one
     *
     * @return array Array keyed by source id containing record id
     */
    public function getDedupData()
    {
        $results = parent::getDedupData();
        if (!empty($this->recordConfig->Record->sort_sources)) {
            uksort(
                $results,
                function ($a, $b) {
                    return strcasecmp(
                        $this->translate("source_$a"),
                        $this->translate("source_$b")
                    );
                }
            );
        }
        return $results;
    }

    /**
     * Get related records (used by RecordDriverRelated - Related module)
     *
     * Returns an associative array of record ids.
     * The array may contain the following keys:
     *   - parents
     *   - children
     *   - continued-from
     *   - other
     *
     * @return array
     */
    public function getRelatedItems()
    {
        return [];
    }

    /**
     * Get work identification keys
     *
     * @return array
     */
    public function getWorkKeys()
    {
        return $this->fields['work_keys_str_mv'] ?? [];
    }

    /**
     * A helper function that merges an array of JSON-encoded URLs
     *
     * @param array $urlArray Array of JSON-encoded URL attributes
     * @param bool  $sources  Whether to store data source of each URL
     *
     * @return array Array of URL information
     */
    protected function mergeURLArray($urlArray, $sources = true)
    {
        $urls = [];
        $sourceFilter = $sources
            && !empty($this->searchSettings['Records']['sources'])
            ? explode(',', $this->searchSettings['Records']['sources']) : [];
        foreach ($urlArray as $url) {
            $newURL = json_decode($url, true);
            // If there's no dedup data, don't display sources either
            if (!$sources) {
                $newURL['source'] = '';
            } elseif ($sourceFilter && !in_array($newURL['source'], $sourceFilter)) {
                continue;
            }
            // Check for duplicates
            $found = false;
            foreach ($urls as &$existingUrl) {
                if ($newURL['url'] == $existingUrl['url']) {
                    $found = true;
                    if (is_array($existingUrl['source'])) {
                        $existingUrl['source'][] = $newURL['source'];
                    } else {
                        $existingUrl['source'] = [
                            $existingUrl['source'],
                            $newURL['source']
                        ];
                    }
                    if (!$existingUrl['text']) {
                        $existingUrl['text'] = $newURL['text'];
                    }
                    break;
                }
            }
            if (!$found) {
                $urls[] = $newURL;
            }
        }
        return $urls;
    }

    /**
     * Check if a URL (typically from getURLs()) is blacklisted based on the URL
     * itself and optionally its description.
     *
     * @param string $url  URL
     * @param string $desc Optional description of the URL
     *
     * @return boolean Whether the URL is blacklisted
     */
    protected function urlBlacklisted($url, $desc = '')
    {
        if (!isset($this->recordConfig->Record->url_blacklist)) {
            return false;
        }
        foreach ($this->recordConfig->Record->url_blacklist as $rule) {
            if (substr($rule, 0, 1) == '/' && substr($rule, -1, 1) == '/') {
                if (preg_match($rule, $url)
                    || ($desc !== '' && preg_match($rule, $desc))
                ) {
                    return true;
                }
            } elseif ($rule == $url || $rule == $desc) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks if any of the URLs contains an audio file and updates
     * the url array acoordingly
     *
     * @param array $urls URLs to be checked for audio files
     *
     * @return array URL array with added audio and codec tag where
     * appropriate
     */
    protected function checkForAudioUrls($urls)
    {
        $newUrls = [];
        foreach ($urls as $url) {
            if (preg_match('/^http(s)?:\/\/.*\.(mp3|wav)$/', $url['url'], $match)) {
                $url['embed'] = 'audio';
                $url['codec'] = $match[2];
            }
            $newUrls[] = $url;
        }
        return $newUrls;
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
        return ['APA', 'Chicago', 'MLA', 'Harvard'];
    }

    /**
     * Return MusicBrainz identifiers from index.
     *
     * @return array
     */
    public function getMusicBrainzIdentifiers()
    {
        return $this->fields['mbid_str_mv'] ?? [];
    }

    /**
     * Get a link for placing a title level hold.
     *
     * @return mixed A url if a hold is possible, boolean false if not
     */
    public function getRealTimeTitleHold()
    {
        $biblioLevel = strtolower($this->tryMethod('getBibliographicLevel'));
        if ($this->hasILS()) {
            if ($this->ils->getTitleHoldsMode() === 'disabled') {
                return false;
            }
            $holdConfig = $this->ils->getConfig(
                'Holds',
                ['id' => $this->getUniqueID()]
            );
            $bibLevels = $holdConfig['titleHoldBibLevels']
                ?? [
                    'monograph', 'monographpart',
                    'serialpart', 'collectionpart'
                ];
            if (in_array($biblioLevel, $bibLevels)) {
                return $this->titleHoldLogic->getHold($this->getUniqueID());
            }
        }
        return false;
    }

    /**
     * Return count of other versions available
     *
     * @return int
     */
    public function getOtherVersionCount()
    {
        if (null === $this->searchService) {
            return false;
        }

        if (!($workKeys = $this->getWorkKeys())) {
            return false;
        }

        if (!isset($this->otherVersionsCount)) {
            $params = new \VuFindSearch\ParamBag();
            $params->add('rows', 0);
            $results = $this->searchService->workExpressions(
                $this->getSourceIdentifier(),
                $this->getUniqueID(),
                $workKeys,
                $params
            );
            $this->otherVersionsCount = $results->getTotal();
        }
        return $this->otherVersionsCount;
    }

    /**
     * Retrieve versions as a search result
     *
     * @param bool $includeSelf Whether to include this record
     * @param int  $count       Maximum number of records to display
     *
     * @return \VuFindSearch\Response\RecordCollectionInterface
     */
    public function getVersions($includeSelf = false, $count = 20)
    {
        if (null === $this->searchService) {
            return false;
        }

        if (!($workKeys = $this->getWorkKeys())) {
            return false;
        }

        if (!isset($this->otherVersions)) {
            $params = new \VuFindSearch\ParamBag();
            $params->add('rows', min($count, 100));
            $this->otherVersions = $this->searchService->workExpressions(
                $this->getSourceIdentifier(),
                $includeSelf ? '' : $this->getUniqueID(),
                $workKeys,
                $params
            );
        }
        return $this->otherVersions;
    }
}
