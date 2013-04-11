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
use VuFindSearch\Backend\Solr\Document\CommitDocument;
use VuFindSearch\Backend\Solr\Document\DeleteDocument;
use VuFindSearch\Backend\Solr\Document\OptimizeDocument;
use VuFindSearch\Backend\Solr\Document\UpdateDocument;

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
        $connector = $this->getConnector($backend);
        $connector->write(new CommitDocument());
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
        $deleteDoc = new DeleteDocument();
        $deleteDoc->addQuery('*:*');
        $connector = $this->getConnector($backend);
        $connector->write($deleteDoc);
    }

    /**
     * Delete an array of IDs from the specified search backend
     *
     * @param string $backend Backend ID
     * @param array  $ids     Record IDs to delete
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
        $connector = $this->getConnector($backend);
        $connector->write(new OptimizeDocument());
    }

    /**
     * Save new record(s) to the index.
     *
     * @param string         $backend Backend ID
     * @param UpdateDocument $doc     Document(s) to save
     *
     * @return void
     */
    public function save($backend, UpdateDocument $doc)
    {
        $connector = $this->getConnector($backend);
        $connector->write($doc);
    }

    /**
     * Get the connector for a specified backend.
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
