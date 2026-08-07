<?php

/**
 * Record "add comment" action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2024.
 * Copyright (C) The National Library of Finland 2026.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Record;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\FormHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\UserContentHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Captcha\Service\CaptchaService;
use VuFind\Db\Service\CommentsServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\Ratings\RatingsService;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Record\ResourcePopulator;
use VuFind\Record\Router as RecordRouter;
use VuFind\RecordTab\TabManager;
use VuFind\Search\Memory as SearchMemory;
use VuFind\Search\ResultScroller;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Session\Helper\FollowupHelper;

use function intval;

/**
 * Record "add comment" action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class AddComentAction extends AbstractRecordAction
{
    /**
     * Constructor.
     *
     * @param SearchMemory             $searchMemory      Search memory
     * @param TabManager               $tabManager        Tab manager
     * @param AuthManager              $authManager       Authentication manager
     * @param RecordLoader             $recordLoader      Record loader
     * @param RecordRouter             $recordRouter      Record router
     * @param ResultScroller           $resultScroller    Result scroller
     * @param array                    $config            VuFind configuration
     * @param CaptchaService           $captchaService    Captcha service
     * @param FollowupHelper           $followupHelper    Followup helper
     * @param ResourcePopulator        $resourcePopulator Resource populator
     * @param CommentsServiceInterface $commentsService   Comments database service
     * @param RatingsService           $ratingsService    Ratings service
     */
    public function __construct(
        SearchMemory $searchMemory,
        TabManager $tabManager,
        AuthManager $authManager,
        RecordLoader $recordLoader,
        RecordRouter $recordRouter,
        ResultScroller $resultScroller,
        #[Autowire(config: 'config')]
        array $config,
        protected CaptchaService $captchaService,
        protected FollowupHelper $followupHelper,
        protected ResourcePopulator $resourcePopulator,
        #[Autowire(container: DbServicePluginManager::class)]
        protected CommentsServiceInterface $commentsService,
        protected RatingsService $ratingsService,
    ) {
        parent::__construct(
            $searchMemory,
            $tabManager,
            $authManager,
            $recordLoader,
            $recordRouter,
            $resultScroller,
            $config
        );
    }

    /**
     * Add a comment.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        // Make sure comments are enabled:
        if (!$this->getHelper(UserContentHelper::class)->commentsEnabled()) {
            throw new ForbiddenException('Comments disabled');
        }

        $captchaActive = $this->captchaService->active('userComments');
        $formHelper = $this->getHelper(FormHelper::class);
        $comment = $this->getPostParam('comment');

        // Force login:
        if (!($user = $this->getUser())) {
            // Validate CAPTCHA before redirecting to login:
            if (!$formHelper->formWasSubmitted($request, 'comment', $captchaActive)) {
                return $this->redirectToRecord('', 'UserComments');
            }

            // Remember comment since POST data will be lost:
            return $this->getHelper(LoginHelper::class)->forceLogin(
                $request,
                $response,
                null,
                compact('comment')
            );
        }

        // Obtain the current record object:
        $driver = $this->loadRecord();

        // Save comment:
        if (empty($comment)) {
            $comment = $this->followupHelper->retrieveAndClear('comment');
        } else {
            // Validate CAPTCHA now only if we're not coming back post-login:
            if (!$formHelper->formWasSubmitted($request, 'comment', $captchaActive)) {
                return $this->redirectToRecord('', 'UserComments');
            }
        }

        // At this point, we should have a comment to save; if we do not,
        // something has gone wrong (or user submitted blank form) and we
        // should do nothing:
        $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
        if (!empty($comment)) {
            $resource = $this->resourcePopulator->getOrCreateResourceForDriver($driver);
            $this->commentsService->addComment($comment, $user, $resource);

            // Save rating if allowed:
            if (
                $driver->isRatingAllowed()
                && '0' !== ($rating = $this->getPostParam('rating', '0'))
            ) {
                $this->ratingsService->saveRating($driver, $user->getId(), intval($rating));
            }

            $flashMessagesHelper->addSuccessMessage('add_comment_success');
        } else {
            $flashMessagesHelper->addErrorMessage('add_comment_fail_blank');
        }

        return $this->redirectToRecord('', 'UserComments');
    }
}
