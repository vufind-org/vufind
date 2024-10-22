<?php

/**
 * SOLR backend.
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\Solr;

use VuFindSearch\Backend\AbstractBackend;
use VuFindSearch\Backend\Exception\BackendException;
use VuFindSearch\Backend\Exception\RemoteErrorException;
use VuFindSearch\Backend\Solr\Document\DocumentInterface;
use VuFindSearch\Backend\Solr\Response\Json\Terms;
use VuFindSearch\Exception\InvalidArgumentException;
use VuFindSearch\Feature\ExtraRequestDetailsInterface;
use VuFindSearch\Feature\GetIdsInterface;
use VuFindSearch\Feature\RandomInterface;
use VuFindSearch\Feature\RetrieveBatchInterface;
use VuFindSearch\Feature\SimilarInterface;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\WorkKeysQuery;
use VuFindSearch\Response\RecordCollectionFactoryInterface;
use VuFindSearch\Response\RecordCollectionInterface;

use function count;
use function is_int;
use function sprintf;

/**
 * SOLR backend.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class Backend extends AbstractBackend implements
    SimilarInterface,
    RetrieveBatchInterface,
    RandomInterface,
    ExtraRequestDetailsInterface,
    GetIdsInterface
{
    /**
     * Limit for records per query in a batch retrieval.
     *
     * @var int
     */
    protected $pageSize = 100;

    /**
     * Connector.
     *
     * @var Connector
     */
    protected $connector;

    /**
     * Query builder.
     *
     * @var QueryBuilder
     */
    protected $queryBuilder = null;

    /**
     * Similar records query builder.
     *
     * @var SimilarBuilder
     */
    protected $similarBuilder = null;

    /**
     * Constructor.
     *
     * @param Connector $connector SOLR connector
     *
     * @return void
     */
    public function __construct(Connector $connector)
    {
        $this->connector    = $connector;
        $this->identifier   = null;
    }

    /**
     * Set the limit for batch queries
     *
     * @param int $pageSize Records per Query
     *
     * @return void
     */
    public function setPageSize($pageSize)
    {
        $this->pageSize = $pageSize;
    }

    /**
     * Perform a search and return record collection.
     *
     * @param AbstractQuery $query  Search query
     * @param int           $offset Search offset
     * @param int           $limit  Search limit
     * @param ParamBag      $params Search backend parameters
     *
     * @return RecordCollectionInterface
     */
    public function search(
        AbstractQuery $query,
        $offset,
        $limit,
        ParamBag $params = null
    ) {
        if ($query instanceof WorkKeysQuery) {
            return $this->workKeysSearch($query, $offset, $limit, $params);
        }
        $json = $this->rawJsonSearch($query, $offset, $limit, $params);
        $collection = $this->createRecordCollection($json);
        $this->injectSourceIdentifier($collection);

        return $collection;
    }

    /**
     * Perform a search and return a raw response.
     *
     * @param AbstractQuery $query  Search query
     * @param int           $offset Search offset
     * @param int           $limit  Search limit
     * @param ParamBag      $params Search backend parameters
     *
     * @return string
     */
    public function rawJsonSearch(
        AbstractQuery $query,
        $offset,
        $limit,
        ParamBag $params = null
    ) {
        $params = $params ?: new ParamBag();
        $this->injectResponseWriter($params);

        $params->set('rows', $limit);
        $params->set('start', $offset);
        $params->mergeWith($this->getQueryBuilder()->build($query, $params));
        return $this->connector->search($params);
    }

    /**
     * Returns some extra details about the search.
     *
     * @return array
     */
    public function getExtraRequestDetails()
    {
        return [
            'solrRequestUrl' => $this->connector->getLastUrl(),
        ];
    }

    /**
     * Clears all accumulated extra request details
     *
     * @return void
     */
    public function resetExtraRequestDetails()
    {
        $this->connector->resetLastUrl();
    }

    /**
     * Perform a search and return record collection of only record identifiers.
     *
     * @param AbstractQuery $query  Search query
     * @param int           $offset Search offset
     * @param int           $limit  Search limit
     * @param ParamBag      $params Search backend parameters
     *
     * @return RecordCollectionInterface
     */
    public function getIds(
        AbstractQuery $query,
        $offset,
        $limit,
        ParamBag $params = null
    ) {
        $params = $params ?: new ParamBag();
        $this->injectResponseWriter($params);

        $params->set('rows', $limit);
        $params->set('start', $offset);
        $flParts = [$this->getConnector()->getUniqueKey()];
        if ($fl = $params->get('fl')) {
            // Merge multiple values if necessary, then split on delimiter:
            $flParts = array_unique(array_merge($flParts, explode(',', implode(',', $fl))));
        }
        $params->set('fl', implode(',', $flParts));
        $params->mergeWith($this->getQueryBuilder()->build($query));
        $response   = $this->connector->search($params);
        $collection = $this->createRecordCollection($response);
        $this->injectSourceIdentifier($collection);

        return $collection;
    }

    /**
     * Get Random records
     *
     * @param AbstractQuery $query  Search query
     * @param int           $limit  Search limit
     * @param ParamBag      $params Search backend parameters
     *
     * @return RecordCollectionInterface
     */
    public function random(
        AbstractQuery $query,
        $limit,
        ParamBag $params = null
    ) {
        $params = $params ?: new ParamBag();
        $this->injectResponseWriter($params);

        $random = rand(0, 1000000);
        $sort = "{$random}_random asc";
        $params->set('sort', $sort);

        return $this->search($query, 0, $limit, $params);
    }

    /**
     * Retrieve a single document.
     *
     * @param string   $id     Document identifier
     * @param ParamBag $params Search backend parameters
     *
     * @return RecordCollectionInterface
     */
    public function retrieve($id, ParamBag $params = null)
    {
        $params = $params ?: new ParamBag();
        $this->injectResponseWriter($params);

        $response   = $this->connector->retrieve($id, $params);
        $collection = $this->createRecordCollection($response);
        $this->injectSourceIdentifier($collection);
        return $collection;
    }

    /**
     * Retrieve a batch of documents.
     *
     * @param array    $ids    Array of document identifiers
     * @param ParamBag $params Search backend parameters
     *
     * @return RecordCollectionInterface
     */
    public function retrieveBatch($ids, ParamBag $params = null)
    {
        $params = $params ?: new ParamBag();

        // Callback function for formatting IDs:
        $formatIds = function ($i) {
            return '"' . addcslashes($i, '"') . '"';
        };

        // Retrieve records a page at a time:
        $results = false;
        while (count($ids) > 0) {
            $currentPage = array_splice($ids, 0, $this->pageSize, []);
            $currentPage = array_map($formatIds, $currentPage);
            $params->set('q', 'id:(' . implode(' OR ', $currentPage) . ')');
            $params->set('start', 0);
            $params->set('rows', $this->pageSize);
            $this->injectResponseWriter($params);
            $next = $this->createRecordCollection(
                $this->connector->search($params)
            );
            if (!$results) {
                $results = $next;
            } else {
                foreach ($next->getRecords() as $record) {
                    $results->add($record);
                }
            }
        }
        $this->injectSourceIdentifier($results);
        return $results;
    }

    /**
     * Return similar records.
     *
     * @param string   $id     Id of record to compare with
     * @param ParamBag $params Search backend parameters
     *
     * @return RecordCollectionInterface
     */
    public function similar($id, ParamBag $params = null)
    {
        $params = $params ?: new ParamBag();
        $this->injectResponseWriter($params);

        $params->mergeWith($this->getSimilarBuilder()->build($id));
        $response   = $this->connector->similar($id, $params);
        $collection = $this->createRecordCollection($response);
        $this->injectSourceIdentifier($collection);
        return $collection;
    }

    /**
     * Return terms from SOLR index.
     *
     * @param string   $field  Index field
     * @param string   $start  Starting term (blank for beginning of list)
     * @param int      $limit  Maximum number of terms
     * @param ParamBag $params Additional parameters
     *
     * @return Terms
     */
    public function terms(
        $field = null,
        $start = null,
        $limit = null,
        ParamBag $params = null
    ) {
        // Support alternate syntax with ParamBag as first parameter:
        if ($field instanceof ParamBag && $params === null) {
            $params = $field;
            $field = null;
        }

        // Create empty ParamBag if none provided:
        $params = $params ?: new ParamBag();
        $this->injectResponseWriter($params);

        // Always enable terms:
        $params->set('terms', 'true');

        // Use parameters if provided:
        if (null !== $field) {
            $params->set('terms.fl', $field);
        }
        if (null !== $start) {
            $params->set('terms.lower', $start);
        }
        if (null !== $limit) {
            $params->set('terms.limit', $limit);
        }

        // Set defaults unless overridden:
        if (!$params->hasParam('terms.lower.incl')) {
            $params->set('terms.lower.incl', 'false');
        }
        if (!$params->hasParam('terms.sort')) {
            $params->set('terms.sort', 'index');
        }

        $response = $this->connector->terms($params);
        $terms = new Terms($this->deserialize($response));
        return $terms;
    }

    /**
     * Obtain information from an alphabetic browse index.
     *
     * @param string   $source      Name of index to search
     * @param string   $from        Starting point for browse results
     * @param int      $page        Result page to return (starts at 0)
     * @param int      $limit       Number of results to return on each page
     * @param ParamBag $params      Additional parameters
     * @param int      $offsetDelta Delta to use when calculating page
     * offset (useful for showing a few results above the highlighted row)
     *
     * @return array
     */
    public function alphabeticBrowse(
        $source,
        $from,
        $page,
        $limit = 20,
        $params = null,
        $offsetDelta = 0
    ) {
        $params = $params ?: new ParamBag();
        $this->injectResponseWriter($params);

        $params->set('from', $from);
        $params->set('offset', ($page * $limit) + $offsetDelta);
        $params->set('rows', $limit);
        $params->set('source', $source);

        $response = null;
        try {
            $response = $this->connector->query('browse', $params);
        } catch (RemoteErrorException $e) {
            $this->refineBrowseException($e);
        }
        return $this->deserialize($response);
    }

    /**
     * Write a document to Solr. Return an array of details about the updated index.
     *
     * @param DocumentInterface $doc     Document to write
     * @param ?int              $timeout Timeout value (null for default)
     * @param string            $handler Handler to use
     * @param ?ParamBag         $params  Search backend parameters
     *
     * @return array
     */
    public function writeDocument(
        DocumentInterface $doc,
        int $timeout = null,
        string $handler = 'update',
        ?ParamBag $params = null
    ) {
        $connector = $this->getConnector();

        // Write!
        $connector->callWithHttpOptions(
            is_int($timeout ?? null) ? compact('timeout') : [],
            'write',
            $doc,
            $handler,
            $params
        );

        // Save the core name in the results in case the caller needs it.
        return ['core' => $connector->getCore()];
    }

    /**
     * Set the query builder.
     *
     * @param QueryBuilder $queryBuilder Query builder
     *
     * @return void
     */
    public function setQueryBuilder(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * Return query builder.
     *
     * Lazy loads an empty default QueryBuilder if none was set.
     *
     * @return QueryBuilder
     */
    public function getQueryBuilder()
    {
        if (!$this->queryBuilder) {
            $this->queryBuilder = new QueryBuilder();
        }
        return $this->queryBuilder;
    }

    /**
     * Set the similar records query builder.
     *
     * @param SimilarBuilder $similarBuilder Similar builder
     *
     * @return void
     */
    public function setSimilarBuilder(SimilarBuilder $similarBuilder)
    {
        $this->similarBuilder = $similarBuilder;
    }

    /**
     * Return similar records query builder.
     *
     * Lazy loads an empty default SimilarBuilder if none was set.
     *
     * @return SimilarBuilder
     */
    public function getSimilarBuilder()
    {
        if (!$this->similarBuilder) {
            $this->similarBuilder = new SimilarBuilder();
        }
        return $this->similarBuilder;
    }

    /**
     * Return the record collection factory.
     *
     * Lazy loads a generic collection factory.
     *
     * @return RecordCollectionFactoryInterface
     */
    public function getRecordCollectionFactory()
    {
        if (!$this->collectionFactory) {
            $this->collectionFactory = new Response\Json\RecordCollectionFactory();
        }
        return $this->collectionFactory;
    }

    /**
     * Return the SOLR connector.
     *
     * @return Connector
     */
    public function getConnector()
    {
        return $this->connector;
    }

    /// Internal API

    /**
     * Create record collection.
     *
     * @param string $json Serialized JSON response
     *
     * @return RecordCollectionInterface
     */
    protected function createRecordCollection($json)
    {
        return $this->getRecordCollectionFactory()
            ->factory($this->deserialize($json));
    }

    /**
     * Deserialize JSON response.
     *
     * @param string $json Serialized JSON response
     *
     * @return array
     *
     * @throws BackendException Deserialization error
     */
    protected function deserialize($json)
    {
        $response = json_decode($json, true);
        $error    = json_last_error();
        if ($error != \JSON_ERROR_NONE) {
            throw new BackendException(
                sprintf('JSON decoding error: %s -- %s', $error, $json)
            );
        }
        $qtime = $response['responseHeader']['QTime'] ?? 'n/a';
        $this->log('debug', 'Deserialized SOLR response', ['qtime' => $qtime]);
        return $response;
    }

    /**
     * Improve the exception message for alphaBrowse errors when appropriate.
     *
     * @param RemoteErrorException $e Exception to clean up
     *
     * @return void
     * @throws RemoteErrorException
     */
    protected function refineBrowseException(RemoteErrorException $e)
    {
        $error = $e->getMessage() . $e->getResponse();
        if (
            strstr($error, 'does not exist') || strstr($error, 'no such table')
            || strstr($error, 'couldn\'t find a browse index')
        ) {
            throw new RemoteErrorException(
                'Alphabetic Browse index missing.  See ' .
                'https://vufind.org/wiki/indexing:alphabetical_heading_browse for ' .
                'details on generating the index.',
                $e->getCode(),
                $e->getResponse(),
                $e->getPrevious()
            );
        }
        throw $e;
    }

    /**
     * Inject response writer and named list implementation into parameters.
     *
     * @param ParamBag $params Parameters
     *
     * @return void
     *
     * @throws InvalidArgumentException Response writer and named list
     * implementation already set to an incompatible type.
     */
    protected function injectResponseWriter(ParamBag $params)
    {
        if (array_diff($params->get('wt') ?: [], ['json'])) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid response writer type: %s',
                    implode(', ', $params->get('wt'))
                )
            );
        }
        if (array_diff($params->get('json.nl') ?: [], ['arrarr'])) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid named list implementation type: %s',
                    implode(', ', $params->get('json.nl'))
                )
            );
        }
        $params->set('wt', ['json']);
        $params->set('json.nl', ['arrarr']);
    }

    /**
     * Return work expressions.
     *
     * @param WorkKeysQuery $query         Search query
     * @param int           $offset        Search offset
     * @param int           $limit         Search limit
     * @param ParamBag      $defaultParams Search backend parameters
     *
     * @return RecordCollectionInterface
     */
    protected function workKeysSearch(
        WorkKeysQuery $query,
        int $offset,
        int $limit,
        ParamBag $defaultParams = null
    ): RecordCollectionInterface {
        $id = $query->getId();
        if ('' === $id) {
            throw new BackendException('Record ID empty in work keys query');
        }
        if (!($workKeys = $query->getWorkKeys())) {
            $recordResponse = $this->connector->retrieve($id);
            $recordCollection = $this->createRecordCollection($recordResponse);
            $record = $recordCollection->first();
            if (!$record || !($workKeys = $record->tryMethod('getWorkKeys'))) {
                return $this->createRecordCollection('{}');
            }
        }

        $params = $defaultParams ? clone $defaultParams : new \VuFindSearch\ParamBag();
        $this->injectResponseWriter($params);
        $params->set('q', "{!terms f=work_keys_str_mv separator=\"\u{001f}\"}" . implode("\u{001f}", $workKeys));
        if (!$query->getIncludeSelf()) {
            $params->add('fq', sprintf('-id:"%s"', addcslashes($id, '"')));
        }
        $params->set('rows', $limit);
        $params->set('start', $offset);
        if (!$params->hasParam('sort')) {
            $params->add('sort', 'publishDateSort desc, title_sort asc');
        }
        $response = $this->connector->search($params);
        $collection = $this->createRecordCollection($response);
        $this->injectSourceIdentifier($collection);
        return $collection;
    }
}
