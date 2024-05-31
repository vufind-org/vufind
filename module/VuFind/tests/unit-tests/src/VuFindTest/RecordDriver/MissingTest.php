<?php

/**
 * Missing Record Driver Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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

namespace VuFindTest\RecordDriver;

use Exception;
use PHPUnit\Framework\ExpectationFailedException;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Service\PluginManager;
use VuFind\Db\Service\ResourceServiceInterface;
use VuFind\RecordDriver\Missing;

/**
 * Missing Record Driver Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MissingTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test that the missing driver leverages ILS details when available to populate
     * missing titles.
     *
     * @return void
     * @throws Exception
     * @throws ExpectationFailedException
     */
    public function testDetermineMissingTitleWithDetails(): void
    {
        $missing = new Missing();
        $missing->setExtraDetail('ils_details', ['title' => 'fake title']);
        $this->assertEquals('fake title', $missing->getShortTitle());
    }

    /**
     * Data provider for testDetermineMissingTitleWithoutDetails
     *
     * @return array
     */
    public static function titleProvider(): array
    {
        return [
            'non-empty title' => ['fake title', 'fake title'],
            'empty title' => ['', 'Title not available'],
        ];
    }

    /**
     * Test that the missing driver looks up title details in the database when necessary.
     *
     * @param string $resourceTitle Title provided by resource entity
     * @param string $expectedTitle Expected title returned by driver
     *
     * @return void
     *
     * @dataProvider titleProvider
     */
    public function testDetermineMissingTitleWithoutDetails(string $resourceTitle, string $expectedTitle): void
    {
        $resource = $this->createMock(ResourceEntityInterface::class);
        $resource->method('getTitle')->willReturn($resourceTitle);
        $resourceService = $this->createMock(ResourceServiceInterface::class);
        $resourceService->expects($this->once())->method('getResourceByRecordId')->with('foo', 'missing')
            ->willReturn($resource);
        $mockManager = $this->createMock(PluginManager::class);
        $mockManager->expects($this->once())->method('get')->with(ResourceServiceInterface::class)
            ->willReturn($resourceService);
        $missing = new Missing();
        $missing->setRawData(['id' => 'foo']);
        $missing->setDbServiceManager($mockManager);
        $this->assertEquals($expectedTitle, $missing->getShortTitle());
    }
}
