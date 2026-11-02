<?php

/**
 * Social statistics home action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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

namespace VuFindAdmin\Action\AdminSocial;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Db\Service\CommentsServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\RatingsServiceInterface;
use VuFind\Db\Service\UserResourceServiceInterface;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Tags\TagsService;
use VuFind\View\GlobalsContainer;
use VuFindAdmin\Action\Admin\AbstractAdminAction;

/**
 * Social statistics home action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class HomeAction extends AbstractAdminAction
{
    /**
     * Constructor.
     *
     * @param GlobalsContainer             $globalsContainer    Globals container
     * @param array                        $config              VuFind configuration
     * @param CommentsServiceInterface     $commentsService     Comments database service
     * @param RatingsServiceInterface      $ratingsService      Ratings database service
     * @param UserResourceServiceInterface $userResourceService User resource database service
     * @param TagsService                  $tagsService         Tags service
     */
    public function __construct(
        GlobalsContainer $globalsContainer,
        #[Autowire(config: 'config')]
        array $config,
        #[Autowire(container: DbServicePluginManager::class)]
        protected CommentsServiceInterface $commentsService,
        #[Autowire(container: DbServicePluginManager::class)]
        protected RatingsServiceInterface $ratingsService,
        #[Autowire(container: DbServicePluginManager::class)]
        protected UserResourceServiceInterface $userResourceService,
        protected TagsService $tagsService
    ) {
        parent::__construct($globalsContainer, $config);
    }

    /**
     * Display social statistics page.
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
        $templateParams = [
            'comments' => $this->commentsService->getStatistics(),
            'ratings' => $this->ratingsService->getStatistics(),
            'favorites' => $this->userResourceService->getStatistics(),
            'tags' => $this->tagsService->getStatistics(),
        ];

        return $this->renderTemplate($request, $response, $templateParams, 'admin/socialstats/home');
    }
}
