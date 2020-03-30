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
