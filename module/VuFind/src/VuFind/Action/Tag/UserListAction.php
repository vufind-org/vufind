<?php

/**
 * Tag list action.
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

namespace VuFind\Action\Tag;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\UserContentHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\ResourceTagsServiceInterface;
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Tag list action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class UserListAction extends AbstractTemplateRenderingAction
{
    /**
     * Array of supported sort options.
     *
     * @var array
     */
    protected array $sortList = [
        'posted desc' => 'sort_created_desc',
        'posted asc' => 'sort_created_asc',
        'title' => 'sort_title',
    ];

    /**
     * Constructor.
     *
     * @param AuthManager                  $authManager         Authentication manager
     * @param ResourceTagsServiceInterface $resourceTagsService Resource tags database service
     */
    public function __construct(
        protected AuthManager $authManager,
        #[Autowire(container: DbServicePluginManager::class)]
        protected ResourceTagsServiceInterface $resourceTagsService,
    ) {
        parent::__construct();
    }

    /**
     * Display list of tags added by the user.
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
        if (!($user = $this->authManager->getUserObject())) {
            return $this->getHelper(LoginHelper::class)->forceLogin($request, $response);
        }
        $userContentHelper = $this->getHelper(UserContentHelper::class);
        if (!$userContentHelper->tagsEnabled()) {
            throw new ForbiddenException('Tags disabled');
        }
        $paging = $userContentHelper->getPagingParams($request, $this->sortList);
        $tags = $userContentHelper->getUserContentRecordTitles(
            $this->resourceTagsService->getResourceTagsPaginator(
                $user->getId(),
                null,
                null,
                $paging['sort'],
                $paging['page'],
                $paging['limit'],
            )
        );
        return $this->renderTemplate(
            $request,
            $response,
            [
                'tags' => $tags,
                'sortList' => $userContentHelper->getSortList($this->sortList, $paging['sort']),
                'params' => $request->getQueryParams(),
            ]
        );
    }
}
