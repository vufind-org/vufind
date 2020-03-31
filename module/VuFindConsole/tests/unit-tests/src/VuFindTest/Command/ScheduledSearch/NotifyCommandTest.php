<?php
/**
 * ScheduledSearch/Notify command test.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2020.
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
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\Command\ScheduledSearch;

use Symfony\Component\Console\Tester\CommandTester;
use VuFindConsole\Command\ScheduledSearch\NotifyCommand;

/**
 * ScheduledSearch/Notify command test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class NotifyCommandTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test behavior when no notifications are waiting to be sent.
     *
     * @return void
     */
    public function testNoNotifications()
    {
        $searchTable = $this->prepareMock(\VuFind\Db\Table\Search::class);
        $searchTable->expects($this->once())->method('getScheduledSearches')
            ->will($this->returnValue([]));
        $command = $this->getCommand(
            [
                'searchTable' => $searchTable,
                'scheduleOptions' => []
            ]
        );
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $expected = "Processing 0 searches\nDone processing searches\n";
        $this->assertEquals($expected, $commandTester->getDisplay());
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Test behavior when notifications are waiting to be sent but there is no
     * matching frequency configuration.
     *
     * @return void
     */
    public function testNotificationWithIllegalFrequency()
    {
        $command = $this->getCommand(
            [
                'searchTable' => $this->getMockSearchTable(),
                'scheduleOptions' => [1 => 'Daily']
            ]
        );
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $expected = "Processing 1 searches\n"
            . "ERROR: Search 1: unknown schedule: 7\nDone processing searches\n";
        $this->assertEquals($expected, $commandTester->getDisplay());
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Test behavior when notifications have already been sent recently.
     *
     * @return void
     */
    public function testNotificationWithRecentExecution()
    {
        $lastDate = date('Y-m-d h:i:s');
        $overrides = [
            'last_notification_sent' => $lastDate,
        ];
        $lastDate = str_replace(' ', 'T', $lastDate) . 'Z';
        $command = $this->getCommand(
            [
                'searchTable' => $this->getMockSearchTable($overrides),
            ]
        );
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $expected = "Processing 1 searches\n"
            . "  Bypassing search 1: previous execution too recent (Weekly, $lastDate)\n"
            . "Done processing searches\n";
        $this->assertEquals($expected, $commandTester->getDisplay());
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Test behavior when notifications are waiting to be sent but user does not
     * exist.
     *
     * @return void
     */
    public function testNotificationsWithMissingUser()
    {
        $command = $this->getCommand(
            [
                'searchTable' => $this->getMockSearchTable(),
            ]
        );
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $expected = "Processing 1 searches\n"
            . "WARNING: Search 1: user 2 does not exist \n"
            . "Done processing searches\n";
        $this->assertEquals($expected, $commandTester->getDisplay());
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Test behavior when notifications are waiting to be sent.
     *
     * @return void
     */
    public function testNotifications()
    {
        $command = $this->getCommand(
            [
                'searchTable' => $this->getMockSearchTable(),
                'userTable' => $this->getMockUserTable(),
            ]
        );
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $expected = "Processing 1 searches\n"
            . "WARNING: Search 1: user 2 does not exist \n"
            . "Done processing searches\n";
        $this->assertEquals($expected, $commandTester->getDisplay());
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Create a list of fake notification objects.
     *
     * @param array $overrides Fields to override in the notification row.
     *
     * @return array
     */
    protected function getMockNotifications($overrides = [])
    {
        $defaults = [
            'id' => 1,
            'user_id' => 2,
            'session_id' => null,
            'folder_id' => null,
            'created' => '2000-01-01 00:00:00',
            'title' => null,
            'saved' => 1,
            'search_object' => serialize($this->getMockSearch()),
            'checksum' => null,
            'notification_frequency' => 7,
            'last_notification_sent' => '2000-01-01 00:00:00',
            'notification_base_url' => 'http://foo',
        ];
        $adapter = $this->prepareMock(\Laminas\Db\Adapter\Adapter::class);
        $row1 = new \VuFind\Db\Row\Search($adapter);
        $row1->populate($overrides + $defaults, true);
        return [$row1];
    }

    /**
     * Get a minified search object
     *
     * @return \VuFind\Search\Minified
     */
    protected function getMockSearch()
    {
        return $this->prepareMock(\VuFind\Search\Minified::class);
    }

    /**
     * Get a mock row representing a user.
     *
     * @return \VuFind\Db\Row\Search
     */
    protected function getMockUserObject()
    {
        $data = [
            'id' => 2,
            'username' => 'foo',
            'email' => 'fake@myuniversity.edu',
        ];
        $adapter = $this->prepareMock(\Laminas\Db\Adapter\Adapter::class);
        $user = new \VuFind\Db\Row\Search($adapter);
        $user->populate($data, true);
        return $user;
    }

    /**
     * Get a notify command for testing.
     *
     * @param array $options Options to override
     *
     * @return NotifyCommand
     */
    protected function getCommand($options = [])
    {
        return new NotifyCommand(
            $this->prepareMock(\VuFind\Crypt\HMAC::class),
            $this->prepareMock(\Laminas\View\Renderer\PhpRenderer::class),
            $this->prepareMock(\VuFind\Search\Results\PluginManager::class),
            $options['scheduleOptions'] ?? [1 => 'Daily', 7 => 'Weekly'],
            new \Laminas\Config\Config([]),
            $this->prepareMock(\VuFind\Mailer\Mailer::class),
            $options['searchTable'] ?? $this->prepareMock(\VuFind\Db\Table\Search::class),
            $options['userTable'] ?? $this->prepareMock(\VuFind\Db\Table\User::class)
        );
    }

    /**
     * Create a mock search table that returns a list of fake notification objects.
     *
     * @param array $overrides Fields to override in the notification row.
     *
     * @return array
     */
    protected function getMockSearchTable($overrides = [])
    {
        $searchTable = $this->prepareMock(\VuFind\Db\Table\Search::class);
        $searchTable->expects($this->once())->method('getScheduledSearches')
            ->will($this->returnValue($this->getMockNotifications($overrides)));
        return $searchTable;
    }

    /**
     * Create a mock user table that returns a fake user object.
     *
     * @return array
     */
    protected function getMockUserTable()
    {
        $user = $this->getMockUserObject();
        $userTable = $this->prepareMock(\VuFind\Db\Table\User::class);
        $userTable->expects($this->any())->method('getById')
            ->with($this->equalTo(2))->will($this->returnValue($user));
        return $userTable;
    }

    /**
     * Prepare a mock object
     *
     * @param string $class Class to mock
     *
     * @return mixed
     */
    protected function prepareMock($class)
    {
        return $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
