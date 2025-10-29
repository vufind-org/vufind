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
use VuFind\Db\PersistenceManager;
use VuFind\Db\Service\ResourceServiceInterface;
use VuFind\Record\Loader;
use VuFind\Record\ResourcePopulator;
use VuFindConsole\Command\Util\UpdateResourceMetadataCommand;

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
        $resourceService = $this->createMock(ResourceServiceInterface::class);
        $resourceService->expects($this->once())
            ->method('findMetadataToUpdate')
            ->with(...$expectedFindParams)
            ->willReturn([]);
        $command = new UpdateResourceMetadataCommand(
            $resourceService,
            $this->createMock(Loader::class),
            $this->createMock(ResourcePopulator::class),
            $this->createMock(PersistenceManager::class)
        );
        $commandTester = new CommandTester($command);
        $commandTester->execute($commandParams);
        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertEquals(
            "Updating resource metadata\nResource metadata update completed\n",
            $commandTester->getDisplay()
        );
    }
}
