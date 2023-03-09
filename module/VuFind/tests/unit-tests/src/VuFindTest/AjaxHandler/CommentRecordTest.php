<?php
/**
 * CommentRecord test class.
 *
 * PHP version 7
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
use VuFind\Db\Row\Resource;
use VuFind\Db\Row\User;
use VuFind\Db\Table\Resource as ResourceTable;
use VuFind\Record\Loader as RecordLoader;
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
class CommentRecordTest extends \VuFindTest\Unit\AjaxHandlerTest
{
    /**
     * Set up a CommentRecord handler for testing.
     *
     * @param bool      $enabled Are comments enabled?
     * @param User|bool $user    Return value for isLoggedIn() in auth manager
     *
     * @return CommentRecord
     */
    protected function getHandler($enabled = true, $user = false)
    {
        // For simplicity, let the top-level container stand in for the plugin
        // managers:
        $this->container
            ->set(\VuFind\Db\Table\PluginManager::class, $this->container);
        $this->container->set('ControllerPluginManager', $this->container);

        // Set up auth manager with user:
        $authManager = $this->getMockAuthManager($user);
        $this->container->set(\VuFind\Auth\Manager::class, $authManager);

        // Set up capability configuration:
        $cfg = new \Laminas\Config\Config(
            ['Social' => ['comments' => $enabled ? 'enabled' : 'disabled']]
        );
        $capabilities = new AccountCapabilities($cfg, $authManager);
        $this->container->set(AccountCapabilities::class, $capabilities);

        // Build the handler:
        $factory = new CommentRecordFactory();
        return $factory($this->container, CommentRecord::class);
    }

    /**
     * Return a mock resource row that expects a specific user and comment.
     *
     * @param string $comment Comment to expect
     * @param User   $user    User to expect
     *
     * @return Resource
     */
    protected function getMockResource($comment, $user)
    {
        $row = $this->container->createMock(Resource::class, ['addComment']);
        $row->expects($this->once())->method('addComment')
            ->with($this->equalTo($comment), $this->equalTo($user))
            ->will($this->returnValue(true));
        return $row;
    }

    /**
     * Test the AJAX handler's response when comments are disabled.
     *
     * @return void
     */
    public function testDisabledResponse()
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
    public function testLoggedOutUser()
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
    public function testEmptyQuery()
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
    public function testSuccessfulTransaction()
    {
        $user = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $user->id = 1;
        $table = $this->container
            ->createMock(ResourceTable::class, ['findResource']);
        $table->expects($this->once())->method('findResource')
            ->with($this->equalTo('foo'), $this->equalTo('Solr'))
            ->will($this->returnValue($this->getMockResource('bar', $user)));
        $this->container->set(ResourceTable::class, $table);

        $driver = $this->getMockBuilder(DefaultRecord::class)->getMock();
        $driver->expects($this->once())
            ->method('isRatingAllowed')
            ->will($this->returnValue(true));
        $driver->expects($this->once())
            ->method('addOrUpdateRating')
            ->with($user->id, 100);
        $recordLoader = $this->container->createMock(RecordLoader::class, ['load']);
        $recordLoader->expects($this->once())
            ->method('load')
            ->with('foo', DEFAULT_SEARCH_BACKEND)
            ->will($this->returnValue($driver));
        $this->container->set(RecordLoader::class, $recordLoader);

        $handler = $this->getHandler(true, $user);
        $post = [
            'id' => 'foo',
            'comment' => 'bar',
            'rating' => '100'
        ];
        $this->assertEquals(
            [
                ['commentId' => true]
            ],
            $handler->handleRequest($this->getParamsHelper([], $post))
        );
    }
}
