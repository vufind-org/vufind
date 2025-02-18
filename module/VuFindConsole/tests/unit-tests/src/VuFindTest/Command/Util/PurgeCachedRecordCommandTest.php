<?php

/**
 * PurgeCachedRecordCommand test.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
 * Copyright (C) The National Library of Finland 2023.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Command\Util;

use Symfony\Component\Console\Tester\CommandTester;
use VuFind\Db\Service\RecordServiceInterface;
use VuFind\Db\Service\ResourceServiceInterface;
use VuFindConsole\Command\Util\PurgeCachedRecordCommand;

/**
 * PurgeCachedRecordCommand test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class PurgeCachedRecordCommandTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Data provider for testBasicOperation
     *
     * @return array
     */
    public static function basicOperationProvider(): array
    {
        return [
            ['Solr', '123', false, true, null],
            ['Solr', '123', false, false, null],
            ['Solr', '123', true, true, true],
            ['Solr', '123', true, true, false],
        ];
    }

    /**
     * Test that the purge cached record action is delegated properly.
     *
     * @param string $source         Source ID
     * @param string $id             Record ID
     * @param bool   $purgeResource  Whether to purge the resource as well
     * @param bool   $recordRetVal   What the record delete method is expected to return
     * @param ?bool  $resourceRetVal What, if anything the resource delete method is expected to return
     *
     * @return void
     *
     * @dataProvider basicOperationProvider
     */
    public function testBasicOperation(
        string $source,
        string $id,
        bool $purgeResource,
        bool $recordRetVal,
        ?bool $resourceRetVal
    ): void {
        $recordService = $this->createMock(RecordServiceInterface::class);
        $recordService->expects($this->once())->method('deleteRecord')->with('123', 'Solr')->willReturn($recordRetVal);

        $resourceService = $this->createMock(ResourceServiceInterface::class);
        if (null !== $resourceRetVal) {
            $resourceService->expects($this->once())->method('deleteResourceByRecordId')->with('123', 'Solr')
                ->willReturn($resourceRetVal);
        }
        $params = compact('source', 'id');
        if ($purgeResource) {
            $params['--purge-resource'] = true;
        }

        $command = new PurgeCachedRecordCommand($recordService, $resourceService);
        $commandTester = new CommandTester($command);
        $commandTester->execute($params);
        $expected = $recordRetVal ? "Cached record deleted\n" : "No cached record found\n";
        if (null !== $resourceRetVal) {
            $expected .= $resourceRetVal ? "Resource deleted\n" : "No resource found\n";
        }
        $this->assertEquals($expected, $commandTester->getDisplay());
        $this->assertEquals(0, $commandTester->getStatusCode());
    }
}
