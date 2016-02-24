<?php
/**
 * Console service for verifying record links.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace FinnaConsole\Service;
use Zend\Db\Sql\Select;

/**
 * Console service for verifying record links.
 *
 * @category VuFind2
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
     * @var Comments
     */
    protected $commentsTable = null;

    /**
     * Comments-Record link table.
     *
     * @var CommentRecords
     */
    protected $commentsRecordTable = null;

    /**
     * SearchRunner
     *
     * @var SearchRunner
     */
    protected $searchRunner = null;

    /**
     * Constructor
     *
     * @param VuFind\Db\Table     $commentsTable       Comments table.
     * @param VuFind\Db\Table     $commentsRecordTable Comments-Record link table.
     * @param VuFind\SearchRunner $searchRunner        SearchRunner
     */
    public function __construct($commentsTable, $commentsRecordTable, $searchRunner)
    {
        $this->commentsTable = $commentsTable;
        $this->commentsRecordTable = $commentsRecordTable;
        $this->searchRunner = $searchRunner;
    }

    /**
     * Run service.
     *
     * @param array $arguments Command line arguments.
     *
     * @return boolean success
     */
    public function run($arguments)
    {
        $count = $fixed = 0;
        $comments = $this->commentsTable->select();
        if (!count($comments)) {
            $this->msg('No comments available for checking');
            return true;
        }

        foreach ($comments as $comment) {
            $commentId = $comment->id;
            $commentsRecord = $this->commentsRecordTable->select(
                function (Select $select) use ($commentId) {
                    $select->where->equalTo('comment_id', $commentId);
                }
            );

            foreach ($commentsRecord as $record) {
                list($source,) = explode('.', $record->record_id, 2);
                if ($source == 'pci') {
                    continue;
                }

                $lookfor = 'local_ids_str_mv:"'
                    . addcslashes($record->record_id, '"') . '"';

                $results = $this->searchRunner->run(
                    ['lookfor' => $lookfor], 'Solr',
                    function ($runner, $params, $searchId) {
                        $params->setLimit(100);
                        $params->setPage(1);
                        $params->resetFacetConfig();
                        $options = $params->getOptions();
                        $options->disableHighlighting();
                    }
                );

                if (!$results instanceof \VuFind\Search\EmptySet\Results
                    && count($results->getResults())
                ) {
                    $results = $results->getResults();
                    $ids = reset($results)->getLocalIds();
                    if ($this->commentsRecordTable->verifyLinks($commentId, $ids)) {
                        $fixed++;
                    }
                }
            }
            ++$count;
            if ($count % 1000 == 0) {
                $this->msg("$count comments checked, $fixed fixed");
            }
        }

        $this->msg("$count comments checked, $fixed fixed");
        return true;
    }
}
