<?php
/**
 * Search API Module Controller
 *
 * PHP Version 5
 *
 * Copyright (C) The National Library of Finland 2015.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA    02111-1307    USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
namespace Finna\Controller;

use VuFind\I18n\TranslatableString;
use Finna\RecordDriver\SolrQdc;

/**
 * SearchApiController Class
 *
 * Controls the Search API functionality
 *
 * @category VuFind2
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
class SearchApiController extends \VuFind\Controller\AbstractSearch
    implements FinnaApiInterface
{
    use FinnaApiTrait;

    protected $recordFields = [
        'accessRestrictions' => 'getAccessRestrictions',
        'alternativeTitles' => 'getAlternativeTitles',
        'authors' => 'getDeduplicatedAuthors',
        'awards' => 'getAwards',
        'bibliographicLevel' => 'getBibliographicLevel',
        'buildings' => 'getBuilding',
        'callNumbers' => 'getCallNumbers',
        'childRecordCount' => 'getChildRecordCount',
        'classifications' => 'getClassifications',
        'cleanDoi' => 'getCleanDOI',
        'cleanIsbn' => 'getCleanISBN',
        'cleanIssn' => 'getCleanISSN',
        'cleanOclcNumber' => 'getCleanOCLCNum',
        'collections' => 'getCollections',
        'comments' => ['method' => 'getRecordComments'],
        'containerIssue' => 'getContainerIssue',
        'containerReference' => 'getContainerReference',
        'containerStartPage' => 'getContainerStartPage',
        'containerTitle' => 'getContainerTitle',
        'containerVolume' => 'getContainerVolume',
        'corporateAuthor' => 'getCorporateAuthor',
        'dedupIds' => ['method' => 'getRecordDedupIds'],
        'dissertationNote' => 'getDissertationNote',
        'edition' => 'getEdition',
        'embeddedComponentParts' => 'getEmbeddedComponentParts',
        'events' => 'getEvents',
        'findingAids' => 'getFindingAids',
        'formats' => 'getFormats',
        'fullRecord' => ['method' => 'getRecordFullRecord'],
        'generalNotes' => 'getGeneralNotes',
        'genres' => 'getGenres',
        'hierarchicalPlaceNames' => 'getHierarchicalPlaceNames',
        'hierarchyParentId' => 'getHierarchyParentId',
        'hierarchyParentTitle' => 'getHierarchyParentTitle',
        'hierarchyTopId' => 'getHierarchyTopId',
        'hierarchyTopTitle' => 'getHierarchyTopTitle',
        'humanReadablePublicationDates' => 'getHumanReadablePublicationDates',
        'id' => 'getUniqueID',
        'identifierString' => ['method' => 'getIdentifier'],
        'imageRights' => ['method' => 'getRecordImageRights'],
        'images' => ['method' => 'getRecordImages'],
        'institutions' => ['method' => 'getRecordInstitutions'],
        'isbns' => 'getISBNs',
        'isCollection' => 'isCollection',
        'isDigitized' => 'isDigitized',
        'isPartOfArchiveSeries' => 'isPartOfArchiveSeries',
        'issns' => 'getISSNs',
        'languages' => 'getLanguages',
        'lccn' => 'getLCCN',
        'manufacturer' => 'getManufacturer',
        'measurements' => 'getMeasurements',
        'newerTitles' => 'getNewerTitles',
        'nonPresenterAuthors' => 'getNonPresenterAuthors',
        'oclc' => 'getOCLC',
        'onlineUrls' => ['method' => 'getRecordOnlineURLs'],
        'openUrl' => 'getOpenUrl',
        'originalLanguages' => 'getOriginalLanguages',
        'otherLinks' => 'getOtherLinks',
        'physicalDescriptions' => 'getPhysicalDescriptions',
        'physicalLocations' => 'getPhysicalLocations',
        'placesOfPublication' => 'getPlacesOfPublication',
        'playingTimes' => 'getPlayingTimes',
        'presenters' => ['method' => 'getRecordPresenters'],
        'previousTitles' => 'getPreviousTitles',
        'productionCredits' => 'getProductionCredits',
        'projectedPublicationDate' => 'getProjectedPublicationDate',
        'publicationDates' => 'getPublicationDates',
        'publicationEndDate' => 'getPublicationEndDate',
        'publicationFrequency' => 'getPublicationFrequency',
        'publicationInfo' => 'getPublicationInfo',
        'publishers' => 'getPublishers',
        'rating' => 'getAverageRating',
        'rawData' => ['method' => 'getRecordRawData'],
        'recordLinks' => ['method' => 'getRecordLinks'],
        'relationshipNotes' => 'getRelationshipNotes',
        'series' => 'getSeries',
        'sfxObjectId' => 'getSfxObjectId',
        'shortTitle' => 'getShortTitle',
        'source' => ['method' => 'getRecordSource'],
        'subjects' => 'getAllSubjectHeadings',
        'subTitle' => 'getSubTitle',
        'summary' => 'getSummary',
        'systemDetails' => 'getSystemDetails',
        'targetAudienceNotes' => 'getTargetAudienceNotes',
        'title' => 'getTitle',
        'titleSection' => 'getTitleSection',
        'titleStatement' => 'getTitleStatement',
        'toc' => 'getTOC',
        'uniformTitles' => 'getUniformTitles',
        'unitId' => 'getUnitID',
        'urls' => ['method' => 'getRecordURLs'],
        'year' => 'getYear'
    ];

    /**
     * Default fields to return if request does not define the fields
     *
     * @var array
     */
    protected $defaultFields = [
        'buildings',
        'comments',
        'formats',
        'id',
        'imageRights',
        'languages',
        'nonPresenterAuthors',
        'onlineUrls',
        'presenters',
        'rating',
        'series',
        'subjects',
        'title',
        'onlineUrls',
        'urls',
        'images',
        'imageRights'
    ];

    /**
     * Record action
     *
     * @return \Zend\Http\Response
     */
    public function recordAction()
    {
        $this->determineOutputMode();

        $request = $this->getRequest()->getQuery()->toArray()
            + $this->getRequest()->getPost()->toArray();

        if (!isset($request['id'])) {
            return $this->output([], self::STATUS_ERROR, 400, 'Missing id');
        }

        $requestedFields = $this->getFieldList($request);

        $loader = $this->getServiceLocator()->get('VuFind\RecordLoader');
        try {
            if (is_array($request['id'])) {
                $results = $loader->loadBatchForSource($request['id']);
            } else {
                $results[] = $loader->load($request['id']);
            }
        } catch (\Exception $e) {
            return $this->output(
                [], self::STATUS_ERROR, 400,
                'Error loading record'
            );
        }

        $records = [];
        foreach ($results as $result) {
            $records[] = $this->getFields($result, $requestedFields);
        }

        $this->filterArrayValues($records);

        $response = [
            'resultCount' => count($results)
        ];
        if ($records) {
            $response['records'] = $records;
        }

        return $this->output($response, self::STATUS_OK);
    }

    /**
     * Search action
     *
     * @return \Zend\Http\Response
     */
    public function searchAction()
    {
        $this->determineOutputMode();

        // Send both GET and POST variables to search class:
        $request = $this->getRequest()->getQuery()->toArray()
            + $this->getRequest()->getPost()->toArray();

        if (isset($request['limit'])
            && (!ctype_digit($request['limit'])
            || $request['limit'] < 0 || $request['limit'] > 100)
        ) {
            return $this->output([], self::STATUS_ERROR, 400, 'Invalid limit');
        }

        $requestedFields = $this->getFieldList($request);

        $facetConfig = $this->getConfig('facets');
        $hierarchicalFacets = isset($facetConfig->SpecialFacets->hierarchical)
            ? $facetConfig->SpecialFacets->hierarchical->toArray()
            : [];

        $runner = $this->getServiceLocator()->get('VuFind\SearchRunner');
        try {
            $results = $runner->run(
                $request,
                $this->searchClassId,
                function ($runner, $params, $searchId) use (
                    $hierarchicalFacets, $request, $requestedFields
                ) {
                    foreach (isset($request['facet']) ? $request['facet'] : []
                       as $facet
                    ) {
                        if (!isset($hierarchicalFacets[$facet])) {
                            $params->addFacet($facet);
                        }
                    }
                    if ($requestedFields) {
                        $limit = isset($request['limit']) ? $request['limit'] : 20;
                        $params->setLimit($limit);
                    } else {
                        $params->setLimit(0);
                    }
                }
            );
        } catch (\Exception $e) {
            return $this->output([], self::STATUS_ERROR, 400, $e->getMessage());
        }

        // If we received an EmptySet back, that indicates that the real search
        // failed due to some kind of syntax error, and we should display a
        // warning to the user; otherwise, we should proceed with normal post-search
        // processing.
        if ($results instanceof \VuFind\Search\EmptySet\Results) {
            return $this->output([], self::STATUS_ERROR, 400, 'Invalid search');
        }

        $requestedFacets = isset($request['facet']) ? $request['facet'] : [];
        $facetFilters = [];
        if (isset($request['facetFilter'])) {
            foreach ($request['facetFilter'] as $filter) {
                list($facetField, $regex) = explode(':', $filter, 2);
                $regex = trim($regex);
                if (substr($regex, 0, 1)  == '"') {
                    $regex = substr($regex, 1);
                }
                if (substr($regex, -1, 1) == '"') {
                    $regex = substr($regex, 0, -1);
                }
                $facetFilters[$facetField][] = $regex;
            }
        }

        $facets = [];
        if ($results->getResultTotal() > 0 && $requestedFacets) {
            $translate = $this->getViewRenderer()->plugin('translate');
            $facets = $results->getFacetList();

            // Get requested hierarchical facets
            $requestedHierarchicalFacets = array_intersect(
                $requestedFacets, $hierarchicalFacets
            );
            if ($requestedHierarchicalFacets) {
                $facetData = $this->getHierarchicalFacetData(
                    $requestedHierarchicalFacets
                );
                foreach ($facetData as $facet => $data) {
                    $facets[$facet]['list'] = $data;
                }
            }

            // Add missing fields to non-hierarchical facets
            $urlHelper = $results->getUrlQuery();
            $paramArray = $urlHelper !== false ? $urlHelper->getParamArray() : null;
            foreach ($facets as $facetKey => &$facetItems) {
                if (in_array($facetKey, $requestedHierarchicalFacets)) {
                    continue;
                }

                foreach ($facetItems['list'] as &$item) {
                    $href = $urlHelper->addFacet(
                        $facetKey, $item['value'], $item['operator'], $paramArray
                    );
                    $item['href'] = $href;
                    if ($facetKey === 'online_boolean') {
                        $item['displayText']
                            = $translate->translate('Available Online');
                    }
                }
            }
            $facets = $this->buildResultFacets($facets, $facetFilters);
        }
        $this->filterArrayValues($facets);

        $records = [];
        foreach ($results->getResults() as $result) {
            $records[] = $this->getFields($result, $requestedFields);
        }

        $this->filterArrayValues($records);

        $response = [
            'resultCount' => $results->getResultTotal(),
        ];
        if ($records) {
            $response['records'] = $records;
        }
        if ($facets) {
            $response['facets'] = $facets;
        }

        return $this->output($response, self::STATUS_OK);
    }

    /**
     * Recursive function to create a facet value list for a single facet
     *
     * @param array $list    Facet items
     * @param array $filters Facet filters
     *
     * @return array
     */
    protected function buildFacetValues($list, $filters = false)
    {
        $result = [];
        $fields = [
            'value', 'displayText', 'count',
            'children', 'href', 'isApplied'
        ];
        foreach ($list as $value) {
            $resultValue = [];
            if ($filters && $this->discardFacetItem($value, $filters)) {
                continue;
            }

            foreach ($value as $key => $item) {
                if (!in_array($key, $fields)) {
                    continue;
                }
                if ($key == 'children') {
                    if (!empty($item)) {
                        $resultValue[$key]
                            = $this->buildFacetValues(
                                $item, $filters
                            );
                    }
                } else {
                    if ($key == 'displayText') {
                        $key = 'translated';
                    }
                    $resultValue[$key] = $item;
                }
            }
            $result[] = $resultValue;
        }
        return $result;
    }

    /**
     * Create the result facet list
     *
     * @param array $facetList All the facet data
     * @param array $filters   Facet filters
     *
     * @return array
     */
    protected function buildResultFacets($facetList, $filters = false)
    {
        $result = [];

        foreach ($facetList as $facetName => $facetData) {
            $result[$facetName]
                = $this->buildFacetValues(
                    $facetData['list'],
                    !empty($filters[$facetName]) ? $filters[$facetName] : false
                );
        }
        return $result;
    }

    /**
     * Recursive function to filter array fields:
     * - remove empty values
     * - convert boolean values to 0/1
     * - force numerically indexed (non-associative) arrays to have numeric keys.
     *
     * @param array $array Array to check
     *
     * @return void
     */
    protected function filterArrayValues(&$array)
    {
        foreach ($array as $key => &$value) {
            if (is_array($value) && !empty($value)) {
                $this->filterArrayValues($value);
                $this->resetArrayIndices($value);
            }

            if ((is_array($value) && empty($value))
                || (is_bool($value) && !$value)
                || $value === null || $value === ''
            ) {
                unset($array[$key]);
            } else if (is_bool($value) || $value === 'true' || $value === 'false') {
                $array[$key] = $value === true || $value === 'true' ? 1 : 0;
            }
        }
        $this->resetArrayIndices($array);
    }

    /**
     * Reset numerical array indices.
     *
     * @param array $array Array
     *
     * @return void
     */
    protected function resetArrayIndices(&$array)
    {
        $isNumeric
            = count(array_filter(array_keys($array), 'is_string')) === 0;
        if ($isNumeric) {
            $array = array_values($array);
        }
    }

    /**
     * Check if facet item should be discarded from the output.
     *
     * @param array $facet   Facet
     * @param array $filters Facet filters
     *
     * @return boolean true
     */
    protected function discardFacetItem($facet, $filters)
    {
        $discard = true;
        array_walk_recursive(
            $facet,
            function ($item, $key) use (&$discard, $filters) {
                if ($discard && $key == 'value') {
                    foreach ($filters as $filter) {
                        $pattern = '/' . addcslashes($filter, '/') . '/';
                        if (preg_match($pattern, $item) === 1) {
                            $discard = false;
                            break;
                        }
                    }
                }
            }
        );
        return $discard;
    }

    /**
     * Get hierarchical facet data for the given facet fields
     *
     * @param array $facets Facet fields
     *
     * @return array
     */
    protected function getHierarchicalFacetData($facets)
    {
        $results = $this->getResultsManager()->get('Solr');
        $params = $results->getParams();
        foreach ($facets as $facet) {
            $params->addFacet($facet, null, false);
        }
        $params->initFromRequest($this->getRequest()->getQuery());

        $facetResults = $results->getFullFieldFacets($facets, false, -1, 'count');

        $facetHelper = $this->getServiceLocator()
            ->get('VuFind\HierarchicalFacetHelper');

        $facetList = [];
        foreach ($facets as $facet) {
            if (empty($facetResults[$facet]['data']['list'])) {
                $facetList[$facet] = [];
                continue;
            }
            $facetList[$facet] = $facetHelper->buildFacetArray(
                $facet,
                $facetResults[$facet]['data']['list'],
                $results->getUrlQuery()
            );
        }

        return $facetList;
    }

    /**
     * Get field list based on the request
     *
     * @param array $request Request params
     *
     * @return array
     */
    protected function getFieldList($request)
    {
        $fieldList = [];
        if (isset($request['field'])) {
            if (!empty($request['field']) && is_array($request['field'])) {
                $fieldList = $request['field'];
            }
        } else {
            $fieldList = $this->defaultFields;
        }
        return $fieldList;
    }

    /**
     * Get fields from a record as an array
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     * @param array                            $fields Fields to get
     *
     * @return array
     */
    protected function getFields($record, $fields)
    {
        $result = [];
        foreach ($fields as $field) {
            if (!isset($this->recordFields[$field])) {
                continue;
            }
            if (isset($this->recordFields[$field]['method'])) {
                $value = $this->{$this->recordFields[$field]['method']}($record);
            } else {
                $value = $record->tryMethod($this->recordFields[$field]);
            }
            $result[$field] = $value;
        }
        // Convert any translation aware string classes to strings
        $translate = $this->getViewRenderer()->plugin('translate');
        array_walk_recursive(
            $result,
            function (&$value) use ($translate) {
                if (is_object($value)) {
                    if ($value instanceof TranslatableString) {
                        $value = [
                            'value' => (string)$value,
                            'translated' => $translate($value)
                        ];
                    } else {
                        $value = (string)$value;
                    }
                }
            }
        );

        return $result;
    }

    /**
     * Get record identifier
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return mixed
     */
    public function getIdentifier($record)
    {
        if ($id = $record->tryMethod('getIdentifier')) {
            if (is_array($id) && count($id) === 1) {
                $id = reset($id);
            }
            return $id;
        }
        return null;
    }

    /**
     * Get comments for a record as an array
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return array
     */
    protected function getRecordComments($record)
    {
        $comments = [];
        foreach ($record->tryMethod('getComments') as $comment) {
            $comments[] = [
                'comment' => $comment->comment,
                'created' => $comment->created,
                'rating' => $comment->finna_rating
            ];
        }
        return $comments;
    }

    /**
     * Get dedup IDs
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return array|null
     */
    protected function getRecordDedupIds($record)
    {
        $dedupData = $record->getDedupData();
        $result = [];
        foreach ($dedupData as $item) {
            $result[] = $item['id'];
        }
        return $result ? $result : null;
    }

    /**
     * Get full record for a record as XML
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return string|null
     */
    protected function getRecordFullRecord($record)
    {
        if ($xml = $record->tryMethod('getFilteredXML')) {
            return $xml;
        }
        $rawData = $record->tryMethod('getRawData');
        return isset($rawData['fullrecord']) ? $rawData['fullrecord'] : null;
    }

    /**
     * Get institutions
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return array|null
     */
    protected function getRecordInstitutions($record)
    {
        if ($institutions = $record->tryMethod('getInstitutions')) {
            $result = [];
            foreach ($institutions as $institution) {
                $result[] = [
                    'value' => $institution,
                    'translated' => $this->translate(
                        "0/$institution/", null, $institution
                    )
                ];
            }
            return $result;
        }
        return null;
    }

    /**
     * Get raw data for a record as an array
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return array
     */
    protected function getRecordRawData($record)
    {
        $rawData = $record->tryMethod('getRawData');
        if ($xml = $record->tryMethod('getFilteredXML')) {
            $rawData['fullrecord'] = $xml;
        }
        // description in MARC and QDC records may contain non-CC0 text, so leave
        // it out
        if ($record instanceof SolrMarc or $record instanceof SolrQdc) {
            unset($rawData['description']);
        }

        // Leave out spelling data
        unset($rawData['spelling']);

        return $rawData;
    }

    /**
     * Get image rights
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return array|null
     */
    protected function getRecordImageRights($record)
    {
        $lang = $this->getServiceLocator()->get('VuFind\Translator')->getLocale();
        $rights = $record->tryMethod('getImageRights', [$lang]);
        return $rights ? $rights : null;
    }

    /**
     * Get images
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return array
     */
    protected function getRecordImages($record)
    {
        $images = [];
        $imageHelper = $this->getViewRenderer()->plugin('recordImage', [$record]);
        $recordHelper = $this->getViewRenderer()->plugin('record');
        $serverUrlHelper = $this->getViewRenderer()->plugin('serverUrl');
        for ($i = 0; $i < $recordHelper($record)->getNumOfRecordImages('large');
            $i++
        ) {
            $images[] = $serverUrlHelper()
                . $imageHelper($recordHelper($record))->getLargeImage($i);
        }
        if (empty($images) && $record->getCleanISBN()) {
            $url = $imageHelper($recordHelper($record))->getLargeImage(0, [], true);
            if ($url) {
                $images[] = $url;
            }
        }
        return $images;
    }

    /**
     * Get presenters
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return array|null
     */
    protected function getRecordPresenters($record)
    {
        $presenters = $record->tryMethod('getPresenters');
        return !empty($presenters['presenters']) || !empty($presenters['details'])
            ? $presenters : null;
    }

    /**
     * Get source
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return array|null
     */
    protected function getRecordSource($record)
    {
        if ($sources = $record->tryMethod('getSource')) {
            $result = [];
            foreach ($sources as $source) {
                $result[] = [
                    'value' => $source,
                    'translated' => $this->translate("source_$source")
                ];
            }
            return $result;
        }
        return null;
    }

    /**
     * Get record links for a record as an array
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return array|null
     */
    protected function getRecordLinks($record)
    {
        $links = $record->tryMethod('getAllRecordLinks');
        if ($links) {
            $translate = $this->getViewRenderer()->plugin('translate');
            $translationEmpty = $this->getViewRenderer()->plugin('translationEmpty');
            foreach ($links as &$link) {
                if (isset($link['title'])
                    && !$translationEmpty($link['title'])
                ) {
                    $link['translated'] = $this->translate($link['title']);
                    unset($link['title']);
                }
            }
        }
        return $links;
    }

    /**
     * Get URLs for a record as an array
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return array|null
     */
    protected function getRecordURLs($record)
    {
        $urls = $record->getURLs();
        $serviceUrls = $record->tryMethod('getServiceUrls');

        $translationEmpty = $this->getViewRenderer()->plugin('translationEmpty');
        if ($urls) {
            foreach ($urls as &$url) {
                if (isset($url['desc'])
                    && !$translationEmpty('link_' . $url['desc'])
                ) {
                    $url['translated'] = $this->translate('link_' . $url['desc']);
                    unset($url['desc']);
                }
            }
        }

        if ($serviceUrls) {
            $source = $record->getDataSource();
            foreach ($serviceUrls as &$url) {
                if (isset($url['desc'])
                    && !$translationEmpty($source . '_' . $url['desc'])
                ) {
                    $url['translated']
                        = $this->translate($source . '_' . $url['desc']);
                    unset($url['desc']);
                }
            }
            $urls += $serviceUrls;
        }
        return $urls ? $urls : null;
    }

    /**
     * Get online URLs for a record as an array
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return array|null
     */
    protected function getRecordOnlineURLs($record)
    {
        $urls = $record->getOnlineURLs();

        if ($urls) {
            $translate = $this->getViewRenderer()->plugin('translate');
            foreach ($urls as &$url) {
                if (isset($url['source'])) {
                    $url['source'] = [
                        'value' => $url['source'],
                        'translated'
                           => $translate->translate('source_' . $url['source'])
                    ];
                }
            }
        }
        return $urls;
    }
}
