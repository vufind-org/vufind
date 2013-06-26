<?php
/**
 * Solr Writer service
 *
 * PHP version 5
 *
 * Copyright (C) Demian Katz 2013.
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
 * @package  Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\Solr;
use VuFind\Db\Table\ChangeTracker, VuFind\Search\BackendManager;
use VuFindSearch\Backend\Solr\Connector;
use VuFindSearch\Backend\Solr\Document\AbstractDocument;
use VuFindSearch\Backend\Solr\Document\CommitDocument;
use VuFindSearch\Backend\Solr\Document\DeleteDocument;
use VuFindSearch\Backend\Solr\Document\OptimizeDocument;

/**
 * Solr Writer service
 *
 * @category VuFind2
 * @package  Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Writer
{
    /**
     * Search backend manager
     *
     * @var BackendManager
     */
    protected $backendManager;

    /**
     * Change tracker database table gateway
     *
     * @var ChangeTracker
     */
    protected $changeTracker;

    /**
     * Constructor
     *
     * @param BackendManager $backend Search backend manager
     * @param ChangeTracker  $tracker Change tracker database table gateway
     */
    public function __construct(BackendManager $backend, ChangeTracker $tracker)
    {
        $this->backendManager = $backend;
        $this->changeTracker = $tracker;
    }

    /**
     * Commit the index.
     *
     * @param string $backend Backend ID
     *
     * @return void
     */
    public function commit($backend)
    {
        // Commit can take a long time -- use a custom timeout:
        $this->writeWithTimeout($backend, new CommitDocument(), 60 * 60);
    }

    /**
     * Delete all records in the index.
     *
     * Note: This does not update the change tracker!
     *
     * @param string $backend Backend ID
     *
     * @return void
     */
    public function deleteAll($backend)
    {
        $this->deleteByQuery($backend, '*:*');
    }

    /**
     * Delete records based on a Solr query.
     *
     * Note: This does not update the change tracker!
     *
     * @param string $backend Backend ID
     * @param string $query   Delete query
     *
     * @return void
     */
    public function deleteByQuery($backend, $query)
    {
        $deleteDoc = new DeleteDocument();
        $deleteDoc->addQuery($query);
        $connector = $this->getConnector($backend);
        $connector->write($deleteDoc);
    }

    /**
     * Delete an array of IDs from the specified search backend
     *
     * @param string $backend Backend ID
     * @param array  $idList  Record IDs to delete
     *
     * @return void
     */
    public function deleteRecords($backend, $idList)
    {
        // Delete IDs:
        $deleteDoc = new DeleteDocument();
        $deleteDoc->addKeys($idList);
        $connector = $this->getConnector($backend);
        $connector->write($deleteDoc);

        // Update change tracker:
        $core = $this->getCore($connector);
        foreach ($idList as $id) {
            $this->changeTracker->markDeleted($core, $id);
        }
    }

    /**
     * Optimize the index.
     *
     * @param string $backend Backend ID
     *
     * @return void
     */
    public function optimize($backend)
    {
        // Optimize can take a long time -- use a custom timeout:
        $this->writeWithTimeout($backend, new OptimizeDocument(), 60 * 60 * 24);
    }

    /**
     * Save new record(s) to the index.
     *
     * @param string           $backend Backend ID
     * @param AbstractDocument $doc     Document(s) to save
     *
     * @return void
     */
    public function save($backend, AbstractDocument $doc)
    {
        $connector = $this->getConnector($backend);
        $connector->write($doc);
    }

    /**
     * Write a document using a custom timeout value.
     *
     * @param string           $backend Backend ID
     * @param AbstractDocument $doc     Document(s) to write
     * @param int              $timeout Timeout value
     *
     * @return void
     */
    protected function writeWithTimeout($backend, AbstractDocument $doc, $timeout)
    {
        $connector = $this->getConnector($backend);

        // Remember the old timeout value and then override it with a different one:
        $oldTimeout = $connector->getTimeout();
        $connector->setTimeout($timeout);

        // Write!
        $connector->write($doc);

        // Restore previous timeout value:
        $connector->setTimeout($oldTimeout);
    }

    /**
     * Get the connector for a specified backend.
     *
     * @param string $backend Backend ID
     *
     * @return Connector
     */
    protected function getConnector($backend)
    {
        $connector = $this->backendManager->get($backend)->getConnector();
        if (!($connector instanceof Connector)) {
            throw new \Exception('Unexpected connector: ' . get_class($connector));
        }
        return $connector;
    }

    /**
     * Extract the Solr core from a connector's URL.
     *
     * @param Connector $connector Solr connector
     *
     * @return string
     */
    protected function getCore(Connector $connector)
    {
        $url = rtrim($connector->getUrl(), '/');
        $parts = explode('/', $url);
        return array_pop($parts);
    }
}
