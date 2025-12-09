<?php

/**
 * UpdateResourceMetadataCommand test.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Command\Util;

use Symfony\Component\Console\Tester\CommandTester;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\PersistenceManager;
use VuFind\Db\Service\ResourceServiceInterface;
use VuFind\Record\Loader;
use VuFind\Record\ResourcePopulator;
use VuFind\RecordDriver\DefaultRecord;
use VuFindConsole\Command\Util\UpdateResourceMetadataCommand;
use VuFindTest\Feature\WithConsecutiveTrait;

/**
 * UpdateResourceMetadataCommand test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class UpdateResourceMetadataCommandTest extends \PHPUnit\Framework\TestCase
{
    use WithConsecutiveTrait;

    /**
     * Data provider for testUpdate
     *
     * @return array
     */
    public static function updateProvider(): array
    {
        return [
            [
                [],
                [null, 100, null, []],
            ],
            [
                ['--min-age' => '30', '--backend' => ['solr'], '--batch' => '10'],
                [null, 10, 30, ['solr']],
            ],
        ];
    }

    /**
     * Test update of records missing metadata.
     *
     * @param array $commandParams      Command-line parameters
     * @param array $expectedFindParams Expected params to resource service's findMetadataToUpdate method
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('updateProvider')]
    public function testUpdate(array $commandParams, array $expectedFindParams)
    {
        $resource = $this->createMock(ResourceEntityInterface::class);
        $resource->expects($this->once())
            ->method('getId')
            ->willReturn(123);
        $resource->expects($this->exactly(2))
            ->method('getRecordId')
            ->willReturn('foo');
        $resource->expects($this->exactly(2))
            ->method('getSource')
            ->willReturn('src');
        $resourceService = $this->createMock(ResourceServiceInterface::class);

        $secondFindParams = $expectedFindParams;
        $secondFindParams[0] = 123;
        $this->expectConsecutiveCalls(
            $resourceService,
            'findMetadataToUpdate',
            [
                $expectedFindParams,
                $secondFindParams,
            ],
            [
                [$resource],
                [],
            ]
        );

        $driver = $this->createMock(DefaultRecord::class);
        $driver->expects($this->once())
            ->method('getUniqueID')
            ->willReturn('foo');
        $loader = $this->createMock(Loader::class);
        $loader->expects($this->once())
            ->method('loadBatch')
            ->with([['id' => 'foo', 'source' => 'src']])
            ->willReturn([$driver]);

        $persistenceManager = $this->createMock(PersistenceManager::class);
        $persistenceManager->expects($this->once())
            ->method('flushEntities');
        $persistenceManager->expects($this->once())
            ->method('clearAllEntities');

        $command = new UpdateResourceMetadataCommand(
            $resourceService,
            $loader,
            $this->createMock(ResourcePopulator::class),
            $persistenceManager
        );
        $commandTester = new CommandTester($command);
        $commandTester->execute($commandParams);
        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertEquals(
            "Updating resource metadata\n1 records updated (0 redirects), 0 records missing\n"
            . "Resource metadata update completed\n",
            $commandTester->getDisplay()
        );
    }
}
