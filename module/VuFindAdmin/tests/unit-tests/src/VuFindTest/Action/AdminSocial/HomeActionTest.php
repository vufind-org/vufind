<?php

/**
 * Unit tests for Socialstats home action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2014-2024.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Action\AdminSocial;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use VuFind\ActionHelper\PermissionHelper;
use VuFind\ActionHelper\PluginManager as HelperPluginManager;
use VuFind\Db\Service\CommentsServiceInterface;
use VuFind\Db\Service\RatingsServiceInterface;
use VuFind\Db\Service\UserResourceServiceInterface;
use VuFind\Tags\TagsService;
use VuFind\View\GlobalsContainer;
use VuFind\View\Renderer\TemplateRendererInterface;
use VuFindAdmin\Action\AdminSocial\HomeAction;
use VuFindTest\Feature\ReflectionTrait;

/**
 * Unit tests for Socialstats home action.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class HomeActionTest extends \PHPUnit\Framework\TestCase
{
    use ReflectionTrait;

    /**
     * Test home action.
     *
     * @return void
     */
    public function testHome(): void
    {
        $mockCommentsStats = ['users' => 5, 'resources' => 7, 'total' => 23];
        $commentsService = $this->createMock(CommentsServiceInterface::class);
        $commentsService->expects($this->once())->method('getStatistics')
            ->willReturn($mockCommentsStats);

        $userResourceStats = ['users' => 5,
            'lists' => 4,
            'resources' => 7,
            'total' => 23,
        ];
        $userResourceService = $this->createMock(UserResourceServiceInterface::class);
        $userResourceService->expects($this->once())->method('getStatistics')
            ->willReturn($userResourceStats);

        $mockRatingsStats = ['users' => 1, 'resources' => 2, 'total' => 3];
        $ratingsService = $this->createMock(RatingsServiceInterface::class);
        $ratingsService->expects($this->once())->method('getStatistics')->willReturn($mockRatingsStats);

        $mockTagStats = ['users' => 31, 'resources' => 32, 'total' => 33];
        $tagService = $this->createMock(TagsService::class);
        $tagService->expects($this->once())->method('getStatistics')
            ->willReturn($mockTagStats);

        $permissionHelper = $this->createMock(PermissionHelper::class);
        $helperManager = $this->createMock(HelperPluginManager::class);
        $helperManager->method('get')->willReturnCallback(
            fn ($name) => match ($name) {
                PermissionHelper::class => $permissionHelper,
                default => throw new \Exception("Unexpected helper requested: $name"),
            }
        );

        $request = new ServerRequest('GET', 'http://localhost');
        $response = new Response();

        $mockRenderer = $this->createMock(TemplateRendererInterface::class);
        $mockRenderer->expects($this->once())
            ->method('renderTemplate')
            ->with(
                $request,
                $response,
                'admin/socialstats/home',
                [
                    'comments' => $mockCommentsStats,
                    'favorites' => $userResourceStats,
                    'tags' => $mockTagStats,
                    'ratings' => $mockRatingsStats,
                ],
            );

        // Build the action to test:
        $action = new HomeAction(
            new GlobalsContainer(),
            [
                'Site' => [
                    'admin_enabled' => true,
                ],
            ],
            $commentsService,
            $ratingsService,
            $userResourceService,
            $tagService
        );
        $action->setTemplateRenderer($mockRenderer);
        $action->setHelperPluginManager($helperManager);
        $this->callMethod($action, 'init');

        // Call the action to confirm expected rendering request:
        $action($request, $response);
    }
}
