<?php
/**
 * CreateHierarchyTreesCommand test.
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
namespace VuFindTest\Command\Util;

use Symfony\Component\Console\Tester\CommandTester;
use VuFind\Record\Loader;
use VuFind\Search\Results\PluginManager;
use VuFindConsole\Command\Util\CreateHierarchyTreesCommand;

/**
 * CreateHierarchyTreesCommand test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class CreateHierarchyTreesCommandTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get mock record loader.
     *
     * @return Loader
     */
    protected function getMockRecordLoader()
    {
        return $this->getMockBuilder(Loader::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Get mock results manager.
     *
     * @return PluginManager
     */
    protected function getMockResultsManager()
    {
        return $this->getMockBuilder(PluginManager::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Get command to test.
     *
     * @param Loader        $loader  Record loader
     * @param PluginManager $results Search results plugin manager
     *
     * @return SuppressedCommand
     */
    protected function getCommand(Loader $loader = null,
        PluginManager $results = null
    ) {
        return new CreateHierarchyTreesCommand(
            $loader ?? $this->getMockRecordLoader(),
            $results ?? $this->getMockResultsManager()
        );
    }

    /**
     * Test skipping everything.
     *
     * @return void
     */
    public function testSkippingEverything()
    {
        $command = $this->getCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            ['--skip-xml' => true, '--skip-json' => true]
        );
        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertEquals("", $commandTester->getDisplay());
    }
}
