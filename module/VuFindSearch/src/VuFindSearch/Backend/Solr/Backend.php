<?php

/**
 * SOLR backend.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */

namespace VuFindSearch\Backend\Solr;

use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\QueryGroup;
use VuFindSearch\Query\Query;
use VuFindSearch\Query\Params;

use VuFindSearch\Response\RecordCollectionInterface;
use VuFindSearch\Response\RecordCollectionFactoryInterface;

use VuFindSearch\Backend\BackendInterface;

use Zend\Log\LoggerInterface;

use VuFindSearch\Backend\Exception\BackendException;

/**
 * SOLR backend.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class Backend implements BackendInterface
{
    /**
     * Record collection factory.
     *
     * @var RecordCollectionFactoryInterface
     */
    protected $collectionFactory;

    /**
     * Dictionaries for spellcheck.
     *
     * @var array
     */
    protected $dictionaries;

    /**
     * Logger, if any.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Connector.
     *
     * @var Connector
     */
    protected $connector;

    /**
     * Backend identifier.
     *
     * @var string
     */
    protected $identifier;

    /**
     * Query builder.
     *
     * @var QueryBuilder
     */
    protected $queryBuilder;

    /**
     * Constructor.
     *
     * @param string    $identifier Backend identifier
     * @param Connector $connector  SOLR connector
     *
     * @return void
     */
    public function __construct ($identifier, Connector $connector)
    {
        $this->connector    = $connector;
        $this->identifier   = $identifier;
        $this->dictionaries = array();
    }

    /**
     * Set the spellcheck dictionaries to use.
     *
     * @param array $dictionaries Spellcheck dictionaries
     *
     * @return void
     */
    public function setDictionaries (array $dictionaries)
    {
        $this->dictionaries = $dictionaries;
    }

    /**
     * Perform a search and return record collection.
     *
     * @param AbstractQuery $query  Search query
     * @param Params        $params Search parameters
     *
     * @return RecordCollectionInterface
     *
     * @todo Disable more SOLR request options when resubmitting for spellcheck
     * @todo Implement merge of spellcheck results
     */
    public function search (AbstractQuery $query, Params $params)
    {
        if ($params->isSpellcheckEnabled()) {
            if (!empty($this->dictionaries)) {
                reset($this->dictionaries);
                $params->setSpellcheckDictionary(current($this->dictionaries));
            } else {
                $this->log('warn', 'Spellcheck requested but no spellcheck dictionary configured');
            }
        }

        $response   = $this->connector->search($query, $params, $this->getQueryBuilder());
        $collection = $this->getRecordCollectionFactory()->factory($this->deserialize($response));
        $this->injectSourceIdentifier($collection);

        // Submit requests for more spelling suggestions
        while (next($this->dictionaries) !== false) {
            $req = $this->connector->getLastRequestParameters();
            $req->set('spellcheck.dictionary', array(current($this->dictionaries)));
            $req->set('rows', array(0));
            $response = $this->connector->resubmit();
        }

        return $collection;
    }

    /**
     * Retrieve a single document.
     *
     * @param string $id Document identifier
     *
     * @return RecordCollectionInterface
     */
    public function retrieve ($id)
    {
        $response   = $this->connector->retrieve($id);
        $collection = $this->getRecordCollectionFactory()->factory($this->deserialize($response));
        $this->injectSourceIdentifier($collection);
        return $collection;
    }

    /**
     * Return similar records.
     *
     * @param string $id Id of record to compare with
     *
     * @return RecordCollectionInterface
     */
    public function similar ($id)
    {
        $response   = $this->connector->similar($id);
        $collection = $this->getRecordCollectionFactory()->factory($this->deserialize($response));
        $this->injectSourceIdentifier($collection);
        return $collection;
    }

    /**
     * Delete a single record.
     *
     * @param string $id Record identifier
     *
     * @return void
     *
     * @todo Currently not implemented in the connector
     */
    public function delete ($id)
    {
        $this->connector->delete($id);
    }

    /**
     * Set the Logger.
     *
     * @param LoggerInterface $logger Logger
     *
     * @return void
     */
    public function setLogger (LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Return query builder.
     *
     * Lazy loads an empty QueryBuilder if none was set.
     *
     * @return QueryBuilder
     */
    public function getQueryBuilder ()
    {
        if (!$this->queryBuilder) {
            $this->queryBuilder = new QueryBuilder();
        }
        return $this->queryBuilder;
    }

    /**
     * Set the query builder.
     *
     * @param QueryBuilder $queryBuilder Query builder
     *
     * @return void
     *
     * @todo Typehint QueryBuilderInterface
     */
    public function setQueryBuilder (QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * Return backend identifier.
     *
     * @return string
     */
    public function getIdentifier ()
    {
        return $this->identifier;
    }

    /**
     * Set the record collection factory.
     *
     * @param RecordCollectionFactoryInterface $factory Factory
     *
     * @return void
     */
    public function setRecordCollectionFactory (RecordCollectionFactoryInterface $factory)
    {
        $this->collectionFactory = $factory;
    }

    /**
     * Return the record collection factory.
     *
     * Lazy loads a generic collection factory.
     *
     * @return RecordCollectionFactoryInterface
     */
    public function getRecordCollectionFactory ()
    {
        if (!$this->collectionFactory) {
            $this->collectionFactory = new Response\Json\RecordCollectionFactory();
        }
        return $this->collectionFactory;
    }

    /**
     * Return the SOLR connector.
     *
     * @return ConnectorInterface
     */
    public function getConnector ()
    {
        return $this->connector;
    }

    /// Internal API

    /**
     * Inject source identifier in record collection and all contained records.
     *
     * @param ResponseInterface $response Response
     *
     * @return void
     */
    protected function injectSourceIdentifier (RecordCollectionInterface $response)
    {
        $response->setSourceIdentifier($this->identifier);
        foreach ($response as $record) {
            $record->setSourceIdentifier($this->identifier);
        }
        return $response;
    }

    /**
     * Send a message to the logger.
     *
     * @param string $level   Log level
     * @param string $message Log message
     * @param array  $context Log context
     *
     * @return void
     */
    protected function log ($level, $message, array $context = array())
    {
        if ($this->logger) {
            $this->logger->$level($message, $context);
        }
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
    protected function deserialize ($json)
    {
        $response = json_decode($json, true);
        $error    = json_last_error();
        if ($error != \JSON_ERROR_NONE) {
            throw new RuntimeException(
                sprintf('JSON decoding error: %s', $error)
            );
        }
        return $response;
    }

}