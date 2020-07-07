<?php
/**
 * AJAX handler to comment on a record.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2018.
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
 * @package  AJAX
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\AjaxHandler;

use Finna\Db\Table\CommentsRecord;
use Laminas\Mvc\Controller\Plugin\Params;
use VuFind\Controller\Plugin\Captcha;
use VuFind\Db\Row\User;
use VuFind\Db\Table\Comments;
use VuFind\Db\Table\Record;
use VuFind\Db\Table\Resource;
use VuFind\Search\SearchRunner;

/**
 * AJAX handler to comment on a record.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CommentRecord extends \VuFind\AjaxHandler\CommentRecord
{
    /**
     * Comments table
     *
     * @var Comments
     */
    protected $commentsTable;

    /**
     * CommentsRecord table
     *
     * @var CommentsRecord
     */
    protected $commentsRecordTable;

    /**
     * Search runner
     *
     * @var SearchRunner
     */
    protected $searchRunner;

    /**
     * Constructor
     *
     * @param Resource        $table          Resource database table
     * @param Captcha         $captcha        Captcha controller plugin
     * @param User|bool       $user           Logged in user (or false)
     * @param bool            $enabled        Are comments enabled?
     * @param Comments        $comments       Comments table
     * @param CommmentsRecord $commentsRecord CommentsRecord table
     * @param SearchRunner    $searchRunner   Search runner
     */
    public function __construct(Resource $table, Captcha $captcha, $user,
        $enabled = true, Comments $comments = null,
        CommentsRecord $commentsRecord = null,
        SearchRunner $searchRunner = null
    ) {
        parent::__construct($table, $captcha, $user, $enabled);
        $this->commentsTable = $comments;
        $this->commentsRecordTable = $commentsRecord;
        $this->searchRunner = $searchRunner;
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        // Make sure comments are enabled:
        if (!$this->enabled) {
            return $this->formatResponse(
                $this->translate('Comments disabled'),
                self::STATUS_HTTP_BAD_REQUEST
            );
        }

        if ($this->user === false) {
            return $this->formatResponse(
                $this->translate('You must be logged in first'),
                self::STATUS_HTTP_NEED_AUTH
            );
        }

        $type = $params->fromPost('type');
        $id = $params->fromPost('id');
        if ($commentId = $params->fromPost('commentId')) {
            // Edit existing comment
            $comment = $params->fromPost('comment');
            if (empty($commentId) || empty($comment)) {
                return $this->formatResponse(
                    $this->translate('An error has occurred'),
                    self::STATUS_HTTP_BAD_REQUEST
                );
            }
            $rating = $params->fromPost('rating');
            $this->commentsTable
                ->edit($this->user->id, $commentId, $comment, $rating);

            $output = ['id' => $commentId];
            if ($rating) {
                $average = $this->commentsTable->getAverageRatingForResource($id);
                $output['rating'] = $average;
            }
            return $this->formatResponse($output);
        }

        if ($type === '1') {
            // Allow only 1 rating/record for each user
            $comments = $this->commentsTable
                ->getForResourceByUser($id, $this->user->id);
            if (count($comments)) {
                return $this->formatResponse(
                    $this->translate('An error has occurred'),
                    self::STATUS_HTTP_ERROR
                );
            }
        }

        $output = parent::handleRequest($params);

        if (isset($output[1]) && 200 !== $output[1]) {
            return $output;
        }

        $commentId = $output[0]['commentId'];

        // Update type
        $this->commentsTable->setType($this->user->id, $commentId, $type);

        // Update rating
        $rating = $params->fromPost('rating');
        $updateRating = $rating !== null && $rating > 0 && $rating <= 5;
        if ($updateRating) {
            $this->commentsTable->setRating($this->user->id, $commentId, $rating);
        }

        // Add comment to deduplicated records
        $results = $this->searchRunner->run(
            ['lookfor' => 'local_ids_str_mv:"' . addcslashes($id, '"') . '"'],
            'Solr',
            function ($runner, $params, $searchId) {
                $params->setLimit(1000);
                $params->setPage(1);
                $params->resetFacetConfig();
                $options = $params->getOptions();
                $options->disableHighlighting();
                $options->spellcheckEnabled(false);
            }
        );
        $ids = [$id];

        if (!$results instanceof \VuFind\Search\EmptySet\Results
            && count($results->getResults())
        ) {
            $results = $results->getResults();
            $ids = reset($results)->getLocalIds();
        }

        $this->commentsRecordTable->addLinks($commentId, $ids);

        if ($updateRating) {
            $average = $this->commentsTable->getAverageRatingForResource($id);
            $output[0]['rating'] = $average;
        }

        return $this->formatResponse($output[0]);
    }
}
