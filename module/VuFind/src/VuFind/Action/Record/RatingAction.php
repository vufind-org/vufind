<?php

/**
 * Record rating action.
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
use VuFind\ActionHelper\ContextHelper;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\ResponseHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Captcha\Service\CaptchaService;
use VuFind\Db\Service\CommentsServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Exception\BadRequest as BadRequestException;
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
 * Record rating action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class RatingAction extends AbstractRecordAction
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
     * Display and add ratings.
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
        // Obtain the current record object:
        $driver = $this->loadRecord();

        // Make sure ratings are allowed for the record:
        if (!$driver->isRatingAllowed()) {
            throw new ForbiddenException('rating_disabled');
        }

        // Save rating, if any, and user has logged in:
        $user = $this->getUser();
        if ($user && null !== ($rating = $this->getPostParam('rating'))) {
            if (
                '' === $rating
                && !($this->config['Social']['remove_rating'] ?? true)
            ) {
                throw new BadRequestException('error_inconsistent_parameters');
            }
            $this->ratingsService->saveRating(
                $driver,
                $user->getId(),
                '' === $rating ? null : intval($rating)
            );
            $this->getHelper(FlashMessagesHelper::class)->addSuccessMessage('rating_add_success');
            if ($this->getHelper(ContextHelper::class)->inLightbox($request)) {
                return $this->getHelper(ResponseHelper::class)->getRefreshResponse($response);
            }
            return $this->redirectToRecord();
        }

        // Display the "add rating" form:
        $currentRating = $user
            ? $this->ratingsService->getRatingData($driver, $user->getId())
            : null;

        return $this->renderTemplate($request, $response, $this->getTemplateParams(compact('currentRating')));
    }
}
