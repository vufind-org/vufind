<?php
/**
 * Search API Module Controller
 *
 * PHP Version 5
 *
 * Copyright (C) The National Library of Finland 2015-2016.
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
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace VuFind\Controller;

use VuFind\I18n\TranslatableString;

/**
 * SearchApiController Class
 *
 * Controls the Search API functionality
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class SearchApiController extends \VuFind\Controller\AbstractSearch
    implements ApiInterface
{
    use ApiTrait;

    /**
     * Permission required for the record endpoint
     *
     * @var string
     */
    protected $recordAccessPermission = 'access.api.Record';

    /**
     * Permission required for the search endpoint
     *
     * @var string
     */
    protected $searchAccessPermission = 'access.api.Search';

    /**
     * Record action
     *
     * @return \Zend\Http\Response
     */
    public function recordAction()
    {
        // Disable session writes
        $this->disableSessionWrites();

        $this->determineOutputMode();

        if ($result = $this->isAccessDenied($this->recordAccessPermission)) {
            return $result;
        }

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
        // Disable session writes
        $this->disableSessionWrites();

        $this->determineOutputMode();

        if ($result = $this->isAccessDenied($this->searchAccessPermission)) {
            return $result;
        }

        // Send both GET and POST variables to search class:
        $request = $this->getRequest()->getQuery()->toArray()
            + $this->getRequest()->getPost()->toArray();

        if (isset($request['limit'])
            && (!ctype_digit($request['limit'])
            || $request['limit'] < 0 || $request['limit'] > 100)
        ) {
            return $this->output([], self::STATUS_ERROR, 400, 'Invalid limit');
        }

        // Sort by relevance by default
        if (!isset($request['sort'])) {
            $request['sort'] = 'relevance';
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

            // Add "missing" fields to non-hierarchical facets to make them similar
            // to hierarchical facets for easier consumption.
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
            $fieldList = $this->defaultRecordFields;
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
            $method = isset($this->recordFields[$field]['method'])
                ? $this->recordFields[$field]['method']
                : $this->recordFields[$field];
            if (method_exists($this, $method)) {
                $value = $this->{$method}($record);
            } else {
                $value = $record->tryMethod($method);
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
     * Get dedup IDs
     *
     * @param \VuFind\RecordDriver\AbstractBase $record Record driver
     *
     * @return array|null
     */
    protected function getRecordDedupIds($record)
    {
        if (!($dedupData = $record->tryMethod('getDedupData'))) {
            return null;
        }
        $result = [];
        foreach ($dedupData as $item) {
            $result[] = $item['id'];
        }
        return $result ? $result : null;
    }

    /**
     * Get full record for a record as XML
     *
     * @param \VuFind\RecordDriver\AbstractBase $record Record driver
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

    protected function getRecordFieldSpec()
    {
        $fields = array_map(
            function ($item) {
                if (isset($item['method'])) {
                    unset($item['method']);
                }
                return $item;
            },
            $this->recordFields
        );
        return $fields;
    }

    /**
     * Get record identifier
     *
     * @param \VuFind\RecordDriver\AbstractBase $record Record driver
     *
     * @return mixed
     */
    protected function getRecordIdentifier($record)
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
     * Get (relative) link to record page
     *
     * @param \VuFind\RecordDriver\AbstractBase $record Record driver
     *
     * @return string
     */
    protected function getRecordPage($record)
    {
        $urlHelper = $this->getViewRenderer()->plugin('recordLink');
        return $urlHelper->getUrl($record);
    }

    /**
     * Get raw data for a record as an array
     *
     * @param \VuFind\RecordDriver\AbstractBase $record Record driver
     *
     * @return array
     */
    protected function getRecordRawData($record)
    {
        $rawData = $record->tryMethod('getRawData');

        // Leave out spelling data
        unset($rawData['spelling']);

        return $rawData;
    }

    /**
     * Get source
     *
     * @param \VuFind\RecordDriver\AbstractBase $record Record driver
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
     * Get URLs
     *
     * @param \VuFind\RecordDriver\AbstractBase $record Record driver
     *
     * @return array
     */
    protected function getRecordURLs($record)
    {
        $recordHelper = $this->getViewRenderer()->plugin('Record');
        return $recordHelper($record)->getLinkDetails();
    }
}
