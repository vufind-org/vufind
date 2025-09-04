<?php

/**
 * Additional functionality for Finna Solr records.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library 2015-2023.
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

namespace Finna\RecordDriver\Feature;

use VuFind\RecordDriver\Feature\VersionAwareInterface;
use VuFindSearch\Command\RetrieveCommand;
use VuFindSearch\Command\SearchCommand;
use VuFindSearch\Query\WorkKeysQuery;

use function in_array;
use function intval;
use function is_array;
use function is_callable;
use function is_string;
use function sprintf;
use function strlen;

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
     * Runtime cache for method results to avoid duplicate processing
     *
     * @var array
     */
    protected $cache = [];

    /**
     * An array of non-displayable formats
     *
     * @var array
     */
    protected $undisplayableFormats;

    /**
     * Return an array of image URLs associated with this record with keys:
     * - urls        Image URLs
     *   - small     Small image (mandatory)
     *   - medium    Medium image (mandatory)
     *   - large     Large image (optional)
     * - description Description text
     * - rights      Rights
     *   - copyright   Copyright (e.g. 'CC BY 4.0') (optional)
     *   - description Human readable description (array)
     *   - link        Link to copyright info
     *
     * @param string $language   Language for copyright information
     * @param bool   $includePdf Whether to include first PDF file when no image
     * links are found
     *
     * @return array
     */
    public function getAllImages($language = 'fi', $includePdf = true)
    {
        return [];
    }

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
     * @param string $language Language
     *
     * @return mixed array with keys:
     *   'copyright'   Copyright (e.g. 'CC BY 4.0')
     *   'link'        Link to copyright info, see IndexRecord::getRightsLink
     *   or false if no access restriction type is defined.
     */
    public function getAccessRestrictionsType($language)
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
     *
     * @deprecated Use getRatingData
     */
    public function getAverageRating()
    {
        $rating = $this->getRatingData();
        return [
            'count' => $rating['count'],
            'average' => $rating['rating'],
        ];
    }

    /**
     * Get text that can be displayed to represent this record in
     * breadcrumbs.
     *
     * @return string Breadcrumb text to represent this record.
     */
    public function getBreadcrumb()
    {
        return $this->getTitle();
    }

    /**
     * Return building from index.
     *
     * @return array
     *
     * @deprecated Use getBuildings
     */
    public function getBuilding()
    {
        return $this->getBuildings();
    }

    /**
     * Return the collection search ID for this record.
     *
     * @return string
     */
    public function getCollectionSearchId(): string
    {
        if ($this->mainConfig->Hierarchy->showFullHierarchyTree ?? false) {
            return $this->getHierarchyTopID()[0] ?? $this->getUniqueID();
        }
        return $this->getUniqueID();
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
                [$lat, $lon] = explode(',', $this->fields['center_coords'], 2);
            } else {
                [$lon, $lat] = explode(' ', $this->fields['center_coords'], 2);
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
     * Get a Date Range from Index Fields
     *
     * @param string $event Event name
     *
     * @return ?array Array of one or two dates or null if not available.
     * If date range is still continuing end year will be an empty string.
     */
    protected function getDateRange($event)
    {
        $daterange = $this->fields["{$event}_daterange"] ?? [];
        if (!$daterange) {
            return null;
        }
        if (
            preg_match(
                '/\[(-?\d{4}).* TO (-?\d{4})/',
                $daterange,
                $matches
            )
        ) {
            $start = (string)(intval($matches[1]));
            $end = (string)(intval($matches[2]));
            if ($end == '9999') {
                // End year is in the future
                return [$start, ''];
            }
            return $end == $start ? [$start] : [$start, $end];
        } elseif (preg_match('/^(-?\d{4})-/', $daterange, $matches)) {
            return [(string)(intval($matches[1]))];
        }
        return null;
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
        return $this->fields['title_full'] ?? '';
    }

    /**
     * Return genres
     *
     * @return array
     */
    public function getGenres()
    {
        return $this->fields['genre'] ?? [];
    }

    /**
     * Return geographic locations (coordinates)
     *
     * @return array
     */
    public function getGeoLocations()
    {
        return $this->fields['location_geo'] ?? [];
    }

    /**
     * Get related places.
     *
     * @return array
     */
    public function getRelatedPlacesExtended()
    {
        return [];
    }

    /**
     * Get the hierarchy_parent_id(s) associated with this item (empty if none).
     *
     * @param string[] $levels Optional list of level types to return
     *
     * @return array
     */
    public function getHierarchyParentID(array $levels = []): array
    {
        return $this->fields['hierarchy_parent_id'] ?? [];
    }

    /**
     * Get the parent title(s) associated with this item (empty if none).
     *
     * @param string[] $levels Optional list of level types to return
     *
     * @return array
     */
    public function getHierarchyParentTitle(array $levels = []): array
    {
        return $this->fields['hierarchy_parent_title'] ?? [];
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
                    ) : [],
            ];
        }

        // Check if this is a merged record
        if (empty($this->fields['merged_child_boolean'])) {
            return [];
        }

        // Find the dedup record
        if (null === $this->searchService) {
            return [];
        }

        // Check for cached data
        if (isset($this->cache[__FUNCTION__])) {
            return $this->cache[__FUNCTION__];
        }

        if (!empty($this->fields['dedup_id_str_mv'])) {
            $command = new RetrieveCommand(
                $this->getSourceIdentifier(),
                $this->fields['dedup_id_str_mv'][0]
            );
            $records = $this->searchService->invoke($command)->getResult()
                ->getRecords();
        }
        if (!isset($records[0])) {
            $this->cache[__FUNCTION__] = [];
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
        $this->cache[__FUNCTION__] = $results;
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
     * @param bool  $raw          Whether to return raw data
     * @param array $excludeTypes If set, will remove types of urls from result
     *
     * @return array
     */
    public function getOnlineURLs($raw = false, $excludeTypes = ['image'])
    {
        if (!isset($this->fields['online_urls_str_mv'])) {
            return [];
        }

        if ($raw) {
            return $this->fields['online_urls_str_mv'];
        }
        $merged = $this->resolveUrlTypes(
            $this->mergeURLArray(
                $this->fields['online_urls_str_mv'],
                true
            )
        );
        if (!$excludeTypes) {
            return $merged;
        }

        $filterFunc = function (array $obj) use ($excludeTypes): bool {
            return !in_array($obj['type'] ?? '', $excludeTypes);
        };
        return array_filter($merged, $filterFunc);
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
        return $this->fields['original_lng_str_mv'] ?? [];
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
     * Get publication date or date range.
     *
     * @return ?array Array of one or two dates or null if not available.
     * If date range is still continuing end year will be an empty string.
     */
    public function getPublicationDateRange()
    {
        $publicationDates = $this->getPublicationDates();
        return $publicationDates ? [$publicationDates[0]] : null;
    }

    /**
     * Return record format.
     *
     * @deprecated Use getRecordFormat()
     *
     * @return string
     */
    public function getRecordType()
    {
        return $this->getRecordFormat();
    }

    /**
     * Return record format.
     *
     * @return string
     */
    public function getRecordFormat()
    {
        return $this->fields['record_format'] ?? $this->fields['recordtype'] ?? '';
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
        $cacheKey = __FUNCTION__ . "/$size";
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $result = parent::getThumbnail($size);

        if (is_array($result) && !isset($result['isbn'])) {
            // Allow also invalid ISBNs
            if ($isbn = $this->getFirstISBN()) {
                $result['invisbn'] = $isbn;
            }
        } elseif (
            is_string($result)
            && is_callable([$this, 'isUrlLoadable'])
            && !$this->isUrlLoadable($result, $this->getUniqueID())
        ) {
            $result = false;
        }
        return $this->cache[$cacheKey] = $result;
    }

    /**
     * Get usage rights (empty if none).
     *
     * @return array
     */
    public function getUsageRights()
    {
        return $this->fields['usage_rights_str_mv'] ?? [];
    }

    /**
     * Return the first ISBN found in the record.
     *
     * @return mixed
     */
    public function getFirstISBN()
    {
        if (isset($this->cache[__FUNCTION__])) {
            return $this->cache[__FUNCTION__];
        }

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
        $this->cache[__FUNCTION__] = $isbn13;
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
        return $this->getSources()[0] ?? '';
    }

    /**
     * Return record sources.
     *
     * @return string
     */
    public function getSources()
    {
        return (array)($this->fields['source_str_mv'] ?? []);
    }

    /**
     * Return main year.
     *
     * @return string|false
     */
    public function getYear()
    {
        return $this->fields['main_date_str'] ?? false;
    }

    /**
     * Get a string representing the first date that the record was indexed.
     *
     * @return string
     */
    public function getFirstIndexed()
    {
        return $this->fields['catalog_date'] ?? $this->fields['first_indexed'] ?? '';
    }

    /**
     * Get an array containing media types as strings.
     *
     * @return array
     */
    public function getMediaTypesAsStrings(): array
    {
        return array_map(
            fn ($entry) => (string)$entry,
            $this->fields['media_type_str_mv'] ?? []
        );
    }

    /**
     * Get array containing ctrlnum.
     *
     * @return array
     */
    public function getControlNumbers(): array
    {
        return $this->fields['ctrlnum'] ?? [];
    }

    /**
     * Get array containing major genres
     *
     * @return array
     */
    public function getMajorGenres(): array
    {
        return $this->fields['major_genre_str_mv'] ?? [];
    }

    /**
     * Get array containing Usage rights extended
     *
     * @return array
     */
    public function getUsageRightsExt(): array
    {
        return $this->fields['usage_rights_ext_str_mv'] ?? [];
    }

    /**
     * Is rating allowed.
     *
     * @return bool
     */
    public function isRatingAllowed(): bool
    {
        if (!parent::isRatingAllowed()) {
            return false;
        }

        $allowed = ['0/Book/', '0/Journal/', '0/Sound/', '0/Video/'];
        $list = array_intersect($allowed, $this->getFormats());
        return !empty($list);
    }

    /**
     * Is rating allowed.
     *
     * @return bool
     *
     * @deprecated Use isRatingAllowed
     */
    public function ratingAllowed()
    {
        return false;
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
            if (!empty($this->ils)) {
                $driver = $this->ils->getDriver(false);
                if ($driver instanceof \VuFind\ILS\Driver\MultiBackend) {
                    $driverConfig = $this->ils->getDriverConfig();
                    [$source] = explode('.', $this->getUniqueID());
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
     * Can the format not be properly displayed?
     *
     * @param string $format Format to check.
     *
     * @return bool
     */
    public function isUndisplayableFormat(string $format): bool
    {
        if (!isset($this->undisplayableFormats)) {
            $this->undisplayableFormats = explode(
                ':',
                $this->mainConfig->Record->undisplayable_file_formats
                ?? 'tif:tiff:3d-pdf:3d model:glb:obj:gltf'
            );
        }
        return in_array($format, $this->undisplayableFormats);
    }

    /**
     * Add or update user's rating for the record.
     *
     * @param int  $userId ID of the user posting the rating
     * @param ?int $rating The user-provided rating, or null to clear any existing
     * rating
     *
     * @return void
     */
    public function addOrUpdateRating(int $userId, ?int $rating): void
    {
        parent::addOrUpdateRating($userId, $rating);

        // Also update ratings of any duplicates:
        $mergedData = $this->getMergedRecordData();
        if (empty($mergedData['records'])) {
            return;
        }
        $source = $this->getSourceIdentifier();
        $resources = $this->getDbTable('Resource');
        foreach ($mergedData['records'] as $record) {
            $resource = $resources->findResource($record['id'], $source);
            $resource->addOrUpdateRating($userId, $rating);
        }
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
        if (
            in_array('1/Book/BookSection/', $formats)
            || in_array('1/Book/eBookSection/', $formats)
        ) {
            return 'BookSection';
        } elseif (in_array('0/Book/', $formats)) {
            return 'Book';
        } elseif (
            in_array('1/Journal/Article/', $formats)
            || in_array('1/Journal/eArticle/', $formats)
        ) {
            return 'Article';
        } elseif (
            in_array('0/Journal/', $formats)
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
            [$source] = explode('.', $id);
            if ($sourceFilter && !in_array($source, $sourceFilter)) {
                continue;
            }
            $results[] = [
                'source' => $source,
                'id' => $id,
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
     * @param bool $load Whether to try to load dedup data if it's not already
     * available
     *
     * @return array Array keyed by source id containing record id
     */
    public function getDedupData(bool $load = false)
    {
        $results = parent::getDedupData();
        if (!$results && $load) {
            $mergedData = $this->getMergedRecordData();
            foreach ($mergedData['records'] ?? [] as $record) {
                $results[$record['source']] = ['id' => $record['id']];
            }
        }
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
     * Returns an associative array of group => records, where each item in
     * records is either a record id or an array with keys:
     * - id: record identifier to search
     * - field (optional): Solr field to search in, defaults to 'identifier'.
     *                     In addition, the query includes a filter that limits the
     *                     results to the same datasource as the issuing record.
     *
     * The array may contain the following keys:
     *   - continued-from
     *   - part-of
     *   - contains
     *   - see-also
     *
     * Examples:
     * - continued-from
     *     - source1.1234
     *     - ['id' => '1234']
     *     - ['id' => '1234', 'field' => 'foo']
     *
     * @return array
     */
    public function getRelatedRecords()
    {
        return [];
    }

    /**
     * Check if a URL (typically from getURLs()) is blocked based on the URL
     * itself and optionally its description.
     *
     * @param string $url  URL
     * @param string $desc Optional description of the URL
     *
     * @return bool Whether the URL is blocked
     */
    public function urlBlocked($url, $desc = '')
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        $allowedSchemes = isset($this->recordConfig->Record->allowed_url_schemes)
            ? $this->recordConfig->Record->allowed_url_schemes->toArray()
            : ['http', 'https', 'tel', 'mailto', 'maps'];
        if (!in_array($scheme, $allowedSchemes)) {
            return true;
        }

        // Keep old setting name for back-compatibility:
        $blocklist = $this->recordConfig->Record->url_blocklist
            ?? $this->recordConfig->Record->url_blacklist
            ?? [];
        if (empty($blocklist)) {
            return false;
        }
        foreach ($blocklist as $rule) {
            if (substr($rule, 0, 1) == '/' && substr($rule, -1, 1) == '/') {
                if (
                    preg_match($rule, $url)
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
                            $newURL['source'],
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
     * Resolve URL types.
     * Each URL is annotated with 'codec' field (taken from the file extension).
     * In addition, image and audio URLs are annotated with 'type' field.
     *
     * @param array $urls URLs
     *
     * @return array URL array with annotated URLs
     */
    protected function resolveUrlTypes($urls)
    {
        $newUrls = [];
        foreach ($urls as $url) {
            if (
                preg_match(
                    '/^http(s)?:\/\/.*\.([a-zA-Z0-9]{3,4})$/',
                    $url['url'],
                    $match
                )
            ) {
                $codec = $match[2];
                $type = $embed = null;
                switch (strtolower($codec)) {
                    case 'wav':
                    case 'mp3':
                        $type = $embed = 'audio';
                        break;
                    case 'jpg':
                    case 'png':
                    case 'tif':
                        $type = 'image';
                        break;
                }
                $url['type'] = $type;
                $url['codec'] = $codec;
                $url['embed'] = $embed;
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
        if (is_callable([$this, 'hasILS']) && $this->hasILS() && isset($this->ils)) {
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
                    'serialpart', 'collectionpart',
                ];
            if (in_array($biblioLevel, $bibLevels) && isset($this->titleHoldLogic)) {
                return $this->titleHoldLogic->getHold($this->getUniqueID());
            }
        }
        return false;
    }

    /**
     * Return count of other versions available
     *
     * Finna: Like VersionAwareTrait's getOtherVersionCount, but adds the call to
     * addVersionsFilters.
     *
     * @return int
     */
    public function getOtherVersionCount()
    {
        if (null === $this->searchService) {
            return false;
        }

        if (!isset($this->otherVersionsCount)) {
            if (!($workKeys = $this->tryMethod('getWorkKeys'))) {
                if (!($this instanceof VersionAwareInterface)) {
                    throw new \Exception(
                        'VersionAwareTrait requires VersionAwareInterface'
                    );
                }
                return false;
            }

            $command = new SearchCommand(
                $this->getSourceIdentifier(),
                new WorkKeysQuery($this->getUniqueID(), false, $workKeys),
                0,
                0
            );
            $results = $this->searchService->invoke($command)->getResult();
            $this->otherVersionsCount = $results->getTotal();
        }
        return $this->otherVersionsCount;
    }

    /**
     * Retrieve versions as a search result
     *
     * Finna: Like VersionAwareTrait's getVersions, but adds the call to
     * addVersionsFilters.
     *
     * @param bool $includeSelf Whether to include this record
     * @param int  $count       Maximum number of records to display
     * @param int  $offset      Start position (0-based)
     *
     * @return \VuFindSearch\Response\RecordCollectionInterface
     */
    public function getVersions($includeSelf = false, $count = 20, $offset = 0)
    {
        if (null === $this->searchService) {
            return false;
        }

        if (!($workKeys = $this->getWorkKeys())) {
            return false;
        }

        if (!isset($this->otherVersions)) {
            $params = new \VuFindSearch\ParamBag();
            $this->addVersionsFilters($params);
            $command = new SearchCommand(
                $this->getSourceIdentifier(),
                new WorkKeysQuery($this->getUniqueID(), $includeSelf, $workKeys),
                $offset,
                $count,
                $params
            );
            $this->otherVersions = $this->searchService->invoke($command)->getResult();
        }
        return $this->otherVersions;
    }

    /**
     * Returns an array of 0 or more record label constants, or null if labels
     * are not enabled in configuration.
     *
     * @return array|null
     */
    public function getRecordLabels()
    {
        return null;
    }

    /**
     * Get the number of child records belonging to this record
     *
     * @return int Number of records
     */
    public function getChildRecordCount()
    {
        if (isset($this->cache[__FUNCTION__])) {
            return $this->cache[__FUNCTION__];
        }
        // Shortcut: if this record is not part of a hierarchy, let's not find out the count.
        if (
            !$this->containerLinking
            || (empty($this->fields['is_hierarchy_id']) && empty($this->fields['hierarchy_parent_id']))
            || null === $this->searchService
        ) {
            return 0;
        }

        $safeId = addcslashes($this->fields['id'], '"');
        $query = new \VuFindSearch\Query\Query(
            'hierarchy_parent_id:"' . $safeId . '"'
        );
        // Disable highlighting for efficiency; not needed here:
        $params = new \VuFindSearch\ParamBag(['hl' => ['false']]);
        $command = new SearchCommand($this->sourceIdentifier, $query, 0, 0, $params);
        $result = $this->searchService->invoke($command)->getResult()->getTotal();
        $this->cache[__FUNCTION__] = $result;
        return $result;
    }

    /**
     * Add versions search filters to params
     *
     * @param \VuFindSearch\ParamBag $paramBag Params
     *
     * @return void
     */
    protected function addVersionsFilters(\VuFindSearch\ParamBag $paramBag)
    {
        // Back-compatibility with the setting in config.ini:
        $filterConf = $this->searchSettings['General']['versions_filter']
            ?? $this->mainConfig->Record->display_versions ?? 'all';
        if ('same_source' === $filterConf) {
            // Add source filter
            $paramBag->add(
                'fq',
                'datasource_str_mv:"' . addcslashes($this->getDataSource(), '"')
                    . '"'
            );
        }
    }

    /**
     * Parse an URL safely. Checks if the URL contains http or https for parse_url to work properly
     *
     * @param string $url       The URL to parse.
     * @param int    $component Specify one of PHP_URL_SCHEME, PHP_URL_HOST, PHP_URL_PORT,
     * PHP_URL_USER, PHP_URL_PASS, PHP_URL_PATH, PHP_URL_QUERY or PHP_URL_FRAGMENT
     * to retrieve just a specific URL component as a string (except when PHP_URL_PORT is given,
     * in which case the return value will be an int).
     *
     * @return int|string|array
     */
    protected function safeParseUrl(string $url, int $component = -1): int|string|array
    {
        if (!$url) {
            return [];
        }
        if (!preg_match('/^https?:/', $url)) {
            $url = '//' . $url;
        }
        return parse_url($url, $component);
    }

    /**
     * Ensure that small, medium and large images do exist in the image array.
     *
     * @param array $images Array containing key 'urls' and respective sizes.
     *
     * @return array Images and duplicate image information
     */
    protected function ensureImageSizes(array $images): array
    {
        $hasSmallImage = isset($images['urls']['small']);
        $hasMediumImage = isset($images['urls']['medium']);
        $hasLargeImage = isset($images['urls']['large']);
        $images['cacheSizes'] = [];
        if (!$hasSmallImage && !$hasMediumImage && !$hasLargeImage) {
            return $images;
        }
        if (!$hasLargeImage) {
            $images['urls']['large'] = $hasMediumImage ? $images['urls']['medium'] : $images['urls']['small'];
            $images['cacheSizes']['large'] = $hasMediumImage ? 'medium' : 'small';
        }
        if (!$hasSmallImage) {
            $images['urls']['small'] = $hasMediumImage ? $images['urls']['medium'] : $images['urls']['large'];
            $images['cacheSizes']['small'] = $hasMediumImage ? 'medium' : 'large';
        }
        if (!$hasMediumImage) {
            $images['urls']['medium'] = $hasSmallImage ? $images['urls']['small'] : $images['urls']['large'];
            $images['cacheSizes']['medium'] = $hasSmallImage ? 'small' : 'large';
        }
        return $images;
    }

    /**
     * Compare the title of current object with items from given array as titles
     *
     * @param array $compare An array of items to compare
     *
     * @return array
     */
    protected function compareWithTitle(array $compare): array
    {
        $compareDone = [];
        $title = str_replace([',', ';'], '', $this->getTitle());
        $compareFull = str_replace([',', ';'], '', implode(' ', $compare));
        if ($compareFull != $title) {
            foreach ($compare as $item) {
                $checkTitle = str_replace([',', ';'], ' ', (string)$item) != $title;
                if ($checkTitle) {
                    $compareDone[] = (string)$item;
                }
            }
        }
        return array_unique($compareDone);
    }
}
