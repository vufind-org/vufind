<?php

/**
 * Solr Writer service
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Solr;

use VuFind\Db\Service\ChangeTrackerServiceInterface;
use VuFindSearch\Backend\Solr\Command\WriteDocumentCommand;
use VuFindSearch\Backend\Solr\Document\CommitDocument;
use VuFindSearch\Backend\Solr\Document\DeleteDocument;
use VuFindSearch\Backend\Solr\Document\DocumentInterface;
use VuFindSearch\Backend\Solr\Document\OptimizeDocument;
use VuFindSearch\ParamBag;
use VuFindSearch\Service;

use function func_get_args;

/**
 * Solr Writer service
 *
 * @category VuFind
 * @package  Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Writer
{
    /**
     * Constructor
     *
     * @param Service                       $searchService Search service
     * @param ChangeTrackerServiceInterface $changeTracker Change tracker database service
     */
    public function __construct(
        protected Service $searchService,
        protected ChangeTrackerServiceInterface $changeTracker
    ) {
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
        $this->write($backend, new CommitDocument(), 60 * 60);
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
        $this->write($backend, $deleteDoc);
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
        $result = $this->write($backend, $deleteDoc);

        // Update change tracker:
        foreach ($idList as $id) {
            $this->changeTracker->markDeleted($result['core'], $id);
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
        $this->write($backend, new OptimizeDocument(), 60 * 60 * 24);
    }

    /**
     * Save new record(s) to the index.
     *
     * @param string            $backend Backend ID
     * @param DocumentInterface $doc     Document(s) to save
     * @param string            $handler Update handler
     * @param ParamBag          $params  Update handler parameters
     *
     * @return void
     */
    public function save(
        $backend,
        DocumentInterface $doc,
        $handler = 'update',
        ParamBag $params = null
    ) {
        $this->write($backend, $doc, null, $handler, $params);
    }

    /**
     * Write a document to the search service. Return the result array from
     * the command.
     *
     * @param string            $backend Backend ID
     * @param DocumentInterface $doc     Document(s) to write
     * @param ?int              $timeout Timeout value (null for default)
     * @param string            $handler Handler to use
     * @param ?ParamBag         $params  Additional backend params (optional)
     *
     * @return array
     */
    protected function write(
        $backend,
        DocumentInterface $doc,
        $timeout = null,
        $handler = 'update',
        $params = null
    ) {
        $command = new WriteDocumentCommand(...func_get_args());
        return $this->searchService->invoke($command)->getResult();
    }
}
