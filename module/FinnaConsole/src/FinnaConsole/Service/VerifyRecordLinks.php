<?php
/**
 * Console service for verifying record links.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace FinnaConsole\Service;

use Zend\Stdlib\RequestInterface as Request;

/**
 * Console service for verifying record links.
 *
 * @category VuFind
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class VerifyRecordLinks extends AbstractService implements ConsoleServiceInterface
{
    /**
     * Comments table.
     *
     * @var \VuFind\Db\Table\Comments
     */
    protected $commentsTable;

    /**
     * CommentsRecord link table.
     *
     * @var \Finna\Db\Table\CommentsRecord
     */
    protected $commentsRecordTable;

    /**
     * Resource table.
     *
     * @var \VuFind\Db\Table\Resource
     */
    protected $resourceTable;

    /**
     * Solr backend
     *
     * @var \VuFindSearch\Backend\Solr\Backend
     */
    protected $solr;

    /**
     * Constructor
     *
     * @param \VuFind\Db\Table\Comments          $comments       Comments table
     * @param \Finna\Db\Table\CommentsRecord     $commentsRecord CommentsRecord table
     * @param \VuFind\Db\Table\Resource          $resource       Resource table
     * @param \VuFindSearch\Backend\Solr\Backend $solr           Search backend
     */
    public function __construct(\VuFind\Db\Table\Comments $comments,
        \Finna\Db\Table\CommentsRecord $commentsRecord,
        \VuFind\Db\Table\Resource $resource,
        \VuFindSearch\Backend\Solr\Backend $solr
    ) {
        $this->commentsTable = $comments;
        $this->commentsRecordTable = $commentsRecord;
        $this->resourceTable = $resource;
        $this->solr = $solr;
    }

    /**
     * Run service.
     *
     * @param array   $arguments Command line arguments.
     * @param Request $request   Full request
     *
     * @return boolean success
     */
    public function run($arguments, Request $request)
    {
        $this->msg('Record link verification started');
        $count = $fixed = 0;
        $comments = $this->commentsTable->select();
        if (!count($comments)) {
            $this->msg('No comments available for checking');
            return true;
        }

        foreach ($comments as $comment) {
            $resource = $this->resourceTable
                ->select(['id' => $comment->resource_id])->current();
            if (!$resource || 'Solr' !== $resource->source) {
                continue;
            }
            $commentId = $comment->id;
            if ($this->verifyLinks($commentId, $resource->record_id)) {
                ++$fixed;
            }
            ++$count;
            if ($count % 1000 == 0) {
                $this->msg("$count comments checked, $fixed links fixed");
            }
        }

        $this->msg(
            "Record link verification completed with $count comments checked, $fixed"
            . ' links fixed'
        );
        return true;
    }

    /**
     * Verify links for a record
     *
     * @param int    $commentId Comment ID
     * @param string $recordId  Record ID
     *
     * @return bool True if changes were made
     */
    protected function verifyLinks($commentId, $recordId)
    {
        // Search directly in Solr to avoid any listeners or filters from interfering
        $query = new \VuFindSearch\Query\Query(
            'local_ids_str_mv:"' . addcslashes($recordId, '"') . '"'
        );

        $params = new \VuFindSearch\ParamBag(
            ['hl' => 'false', 'spellcheck' => 'false', 'sort' => '']
        );
        $records = $this->solr->search($query, 0, 1, $params)->getRecords();

        // This preserves the comment-record links for a comment when all
        // links point to non-existent records. Dangling links have no
        // effect in the UI. If a record was temporarily unavailable and
        // gets re-added to the index with the same ID, the comment is shown
        // in the UI again.
        if ($records) {
            $ids = $records[0]->getLocalIds();
        } else {
            $ids = [$recordId];
        }
        if ($this->commentsRecordTable->verifyLinks($commentId, $ids)) {
            return true;
        }
        return false;
    }
}
