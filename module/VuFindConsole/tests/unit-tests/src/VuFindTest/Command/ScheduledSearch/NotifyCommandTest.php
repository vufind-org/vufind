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
        $command = new NotifyCommand(
            $this->prepareMock(\VuFind\Crypt\HMAC::class),
            $this->prepareMock(\Laminas\View\Renderer\PhpRenderer::class),
            $this->prepareMock(\VuFind\Search\Results\PluginManager::class),
            [],
            new \Laminas\Config\Config([]),
            $this->prepareMock(\VuFind\Mailer\Mailer::class),
            $searchTable,
            $this->prepareMock(\VuFind\Db\Table\User::class)
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
        $searchTable = $this->prepareMock(\VuFind\Db\Table\Search::class);
        $searchTable->expects($this->once())->method('getScheduledSearches')
            ->will($this->returnValue($this->getMockNotifications()));
        $command = new NotifyCommand(
            $this->prepareMock(\VuFind\Crypt\HMAC::class),
            $this->prepareMock(\Laminas\View\Renderer\PhpRenderer::class),
            $this->prepareMock(\VuFind\Search\Results\PluginManager::class),
            [1 => 'Daily'],
            new \Laminas\Config\Config([]),
            $this->prepareMock(\VuFind\Mailer\Mailer::class),
            $searchTable,
            $this->prepareMock(\VuFind\Db\Table\User::class)
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
        $searchTable = $this->prepareMock(\VuFind\Db\Table\Search::class);
        $searchTable->expects($this->once())->method('getScheduledSearches')
            ->will($this->returnValue($this->getMockNotifications($overrides)));
        $command = new NotifyCommand(
            $this->prepareMock(\VuFind\Crypt\HMAC::class),
            $this->prepareMock(\Laminas\View\Renderer\PhpRenderer::class),
            $this->prepareMock(\VuFind\Search\Results\PluginManager::class),
            [1 => 'Daily', 7 => 'Weekly'],
            new \Laminas\Config\Config([]),
            $this->prepareMock(\VuFind\Mailer\Mailer::class),
            $searchTable,
            $this->prepareMock(\VuFind\Db\Table\User::class)
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
    public function testNotifications()
    {
        $searchTable = $this->prepareMock(\VuFind\Db\Table\Search::class);
        $searchTable->expects($this->once())->method('getScheduledSearches')
            ->will($this->returnValue($this->getMockNotifications()));
        $command = new NotifyCommand(
            $this->prepareMock(\VuFind\Crypt\HMAC::class),
            $this->prepareMock(\Laminas\View\Renderer\PhpRenderer::class),
            $this->prepareMock(\VuFind\Search\Results\PluginManager::class),
            [1 => 'Daily', 7 => 'Weekly'],
            new \Laminas\Config\Config([]),
            $this->prepareMock(\VuFind\Mailer\Mailer::class),
            $searchTable,
            $this->prepareMock(\VuFind\Db\Table\User::class)
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
            'search_object' => 'foo',
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
