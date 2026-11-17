<?php

/**
 * Abstract base class for Lists actions.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2023-2026.
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

namespace VuFind\Action\Lists;

use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Config\Feature\EmailSettingsTrait;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\Db\Service\UserResourceServiceInterface;
use VuFind\Favorites\FavoritesService;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\Recommend\PluginManager as RecommendPluginManager;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Record\Router as RecordRouter;
use VuFind\Search\SearchRunner;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Tags\TagsService;

/**
 * Abstract base class for Lists actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractListsAction extends AbstractTemplateRenderingAction implements TranslatorAwareInterface
{
    use EmailSettingsTrait;
    use TranslatorAwareTrait;

    /**
     * Constructor.
     *
     * @param AuthManager                  $authManager            Authentication manager
     * @param UserListServiceInterface     $userListService        User list database service
     * @param UserResourceServiceInterface $userResourceService    User resource database service
     * @param FavoritesService             $favoritesService       Favorites service
     * @param RecordLoader                 $recordLoader           Record loader
     * @param RecordRouter                 $recordRouter           Record router
     * @param TagsService                  $tagsService            Tags service
     * @param SearchRunner                 $searchRunner           Search runner
     * @param RecommendPluginManager       $recommendPluginManager Recommendation plugin manager
     */
    public function __construct(
        protected AuthManager $authManager,
        #[Autowire(container: DbServicePluginManager::class)]
        protected UserListServiceInterface $userListService,
        #[Autowire(container: DbServicePluginManager::class)]
        protected UserResourceServiceInterface $userResourceService,
        protected FavoritesService $favoritesService,
        protected RecordLoader $recordLoader,
        protected RecordRouter $recordRouter,
        protected TagsService $tagsService,
        protected SearchRunner $searchRunner,
        protected RecommendPluginManager $recommendPluginManager,
    ) {
        parent::__construct();
    }
}
