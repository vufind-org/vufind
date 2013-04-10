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
use VuFindSearch\Backend\Solr\Document\DeleteDocument;
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
