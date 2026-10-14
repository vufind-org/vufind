<?php

/**
 * Abtract base class for tags actions.
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

namespace VuFindAdmin\Action\AdminTags;

use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\ResourceServiceInterface;
use VuFind\Db\Service\ResourceTagsServiceInterface;
use VuFind\Db\Service\TagServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Tags\TagsService;
use VuFind\View\GlobalsContainer;
use VuFindAdmin\Action\Admin\AbstractAdminAction;

/**
 * Abtract base class for tags actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractTagsAction extends AbstractAdminAction
{
    /**
     * Constructor.
     *
     * @param GlobalsContainer             $globalsContainer    Globals container
     * @param array                        $config              VuFind configuration
     * @param ResourceTagsServiceInterface $resourceTagsService Resource tags database service
     * @param TagServiceInterface          $tagDbService        Tag database service
     * @param UserServiceInterface         $userService         User database service
     * @param ResourceServiceInterface     $resourceService     Resource database service
     * @param TagsService                  $tagsService         Tags service
     */
    public function __construct(
        GlobalsContainer $globalsContainer,
        #[Autowire(config: 'config')]
        array $config,
        #[Autowire(container: DbServicePluginManager::class)]
        protected ResourceTagsServiceInterface $resourceTagsService,
        #[Autowire(container: DbServicePluginManager::class)]
        protected TagServiceInterface $tagDbService,
        #[Autowire(container: DbServicePluginManager::class)]
        protected UserServiceInterface $userService,
        #[Autowire(container: DbServicePluginManager::class)]
        protected ResourceServiceInterface $resourceService,
        protected TagsService $tagsService,
    ) {
        parent::__construct($globalsContainer, $config);
    }

    /**
     * Gets a list of unique resources based on the url params.
     *
     * @return array[]
     */
    protected function getUniqueResources(): array
    {
        return $this->resourceTagsService->getUniqueResources(
            $this->convertFilter($this->getPostOrQueryParam('user_id', preferQuery: true)),
            $this->convertFilter($this->getPostOrQueryParam('resource_id', preferQuery: true)),
            $this->convertFilter($this->getPostOrQueryParam('tag_id', preferQuery: true))
        );
    }

    /**
     * Gets a list of unique tags based on the url params.
     *
     * @return array[]
     */
    protected function getUniqueTags(): array
    {
        return $this->tagsService->getUniqueTags(
            $this->convertFilter($this->getPostOrQueryParam('user_id', preferQuery: true)),
            $this->convertFilter($this->getPostOrQueryParam('resource_id', preferQuery: true)),
            $this->convertFilter($this->getPostOrQueryParam('tag_id', preferQuery: true))
        );
    }

    /**
     * Gets a list of unique users based on the url params.
     *
     * @return array[]
     */
    protected function getUniqueUsers(): array
    {
        return $this->resourceTagsService->getUniqueUsers(
            $this->convertFilter($this->getPostOrQueryParam('user_id', preferQuery: true)),
            $this->convertFilter($this->getPostOrQueryParam('resource_id', preferQuery: true)),
            $this->convertFilter($this->getPostOrQueryParam('tag_id', preferQuery: true))
        );
    }

    /**
     * Converts empty params and "ALL" to null.
     *
     * @param ?string $value A parameter to check
     *
     * @return ?string A modified parameter
     */
    protected function convertFilter(?string $value): ?string
    {
        return ('ALL' !== $value && '' !== $value) ? $value : null;
    }
}
