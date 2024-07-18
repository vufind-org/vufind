<?php

/**
 * CommentRecord test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\AjaxHandler;

use VuFind\AjaxHandler\CommentRecord;
use VuFind\AjaxHandler\CommentRecordFactory;
use VuFind\Config\AccountCapabilities;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\CommentsServiceInterface;
use VuFind\Ratings\RatingsService;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Record\ResourcePopulator;
use VuFind\RecordDriver\DefaultRecord;

/**
 * CommentRecord test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class CommentRecordTest extends \VuFindTest\Unit\AjaxHandlerTestCase
{
    /**
     * Set up a CommentRecord handler for testing.
     *
     * @param bool                 $enabled Are comments enabled?
     * @param ?UserEntityInterface $user    Return value for getUserObject() in auth manager
     *
     * @return CommentRecord
     */
    protected function getHandler(bool $enabled = true, ?UserEntityInterface $user = null): CommentRecord
    {
        // For simplicity, let the top-level container stand in for the plugin
        // managers:
        $this->container
            ->set(\VuFind\Db\Service\PluginManager::class, $this->container);
        $this->container->set('ControllerPluginManager', $this->container);

        // Set up auth manager with user:
        $authManager = $this->getMockAuthManager($user);
        $this->container->set(\VuFind\Auth\Manager::class, $authManager);

        // Set up capability configuration:
        $cfg = new \Laminas\Config\Config(
            ['Social' => ['comments' => $enabled ? 'enabled' : 'disabled']]
        );
        $capabilities = new AccountCapabilities(
            $cfg,
            function () use ($authManager) {
                return $authManager;
            }
        );
        $this->container->set(AccountCapabilities::class, $capabilities);

        // Build the handler:
        $factory = new CommentRecordFactory();
        return $factory($this->container, CommentRecord::class);
    }

    /**
     * Test the AJAX handler's response when comments are disabled.
     *
     * @return void
     */
    public function testDisabledResponse(): void
    {
        $handler = $this->getHandler(false);
        $this->assertEquals(
            ['Comments disabled', 400],
            $handler->handleRequest($this->getParamsHelper())
        );
    }

    /**
     * Test the AJAX handler's response when no one is logged in.
     *
     * @return void
     */
    public function testLoggedOutUser(): void
    {
        $handler = $this->getHandler(true);
        $this->assertEquals(
            ['You must be logged in first', 401],
            $handler->handleRequest($this->getParamsHelper())
        );
    }

    /**
     * Test the AJAX handler's response when the query is empty.
     *
     * @return void
     */
    public function testEmptyQuery(): void
    {
        $handler = $this->getHandler(true, $this->getMockUser());
        $this->assertEquals(
            ['bulk_error_missing', 400],
            $handler->handleRequest($this->getParamsHelper())
        );
    }

    /**
     * Test a successful scenario.
     *
     * @return void
     */
    public function testSuccessfulTransaction(): void
    {
        $user = $this->createMock(UserEntityInterface::class);
        $user->method('getId')->willReturn(1);
        $resource = $this->createMock(ResourceEntityInterface::class);
        $this->container->get(ResourcePopulator::class)->expects($this->once())
            ->method('getOrCreateResourceForRecordId')
            ->with('foo', 'Solr')
            ->willReturn($resource);
        $mockCommentsService = $this->createMock(CommentsServiceInterface::class);
        $mockCommentsService->expects($this->once())->method('addComment')
            ->with('bar', $user, $resource)
            ->willReturn(1);
        $this->container->set(CommentsServiceInterface::class, $mockCommentsService);

        $driver = $this->getMockBuilder(DefaultRecord::class)->getMock();
        $driver->expects($this->once())
            ->method('isRatingAllowed')
            ->willReturn(true);
        $ratingService = $this->container->get(RatingsService::class);
        $ratingService->expects($this->once())
            ->method('saveRating')
            ->with($driver, $user->getId(), 100);
        $recordLoader = $this->container->createMock(RecordLoader::class, ['load']);
        $recordLoader->expects($this->once())
            ->method('load')
            ->with('foo', DEFAULT_SEARCH_BACKEND)
            ->willReturn($driver);
        $this->container->set(RecordLoader::class, $recordLoader);

        $handler = $this->getHandler(true, $user);
        $post = [
            'id' => 'foo',
            'comment' => 'bar',
            'rating' => '100',
        ];
        $this->assertEquals(
            [
                ['commentId' => true],
            ],
            $handler->handleRequest($this->getParamsHelper([], $post))
        );
    }
}
