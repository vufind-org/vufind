<?php

/**
 * Trait which returns pre-configured db mocks
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace FinnaTest\Traits;

use Finna\Db\Service\AccessTokenService;
use Laminas\Db\ResultSet\ResultSet;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Db\Row\AccessToken;
use VuFind\Db\Table\AccessToken as TableAccessToken;

/**
 * Trait which returns pre-configured db mocks
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait MockServicesTrait
{
    /**
     * Get Finna access token service as a mocked service
     *
     * @param array $dbEntities Array containing db entities to use as a database
     *
     * @return MockObject
     */
    public function getFinnaAccessTokenService(array $dbEntities = []): MockObject
    {
        $accessTokenService = $this->getMockBuilder(AccessTokenService::class)->onlyMethods(['getDbTable'])
            ->disableOriginalConstructor()->getMock();

        $accessTokens = [];
        foreach ($dbEntities as $entity) {
            $accessTokens[] = $this->getMockedRowObject(AccessToken::class, $entity);
        }
        $accessTokenTable = $this->getMockedTableObject(TableAccessToken::class, $accessTokens);
        $accessTokenService->expects($this->any())->method('getDbTable')->willReturn($accessTokenTable);
        return $accessTokenService;
    }

    /**
     * Create a mocked row object
     *
     * @param string $name Row class name
     * @param array  $data Array containing data as key => value
     *
     * @return MockObject
     */
    public function getMockedRowObject(string $name, array $data): MockObject
    {
        $mockedRow = $this->getMockBuilder($name)->disableOriginalConstructor()->onlyMethods(['__get'])->getMock();
        $map = [];
        foreach ($data as $key => $value) {
            $map[] = [$key, $value];
        }
        $mockedRow->expects($this->any())->method('__get')->willReturnMap($map);
        return $mockedRow;
    }

    /**
     * Create a mocked table object
     *
     * @param string $name   Table class name
     * @param array  $dbRows Database rows
     *
     * @return MockObject
     */
    public function getMockedTableObject(string $name, array $dbRows): MockObject
    {
        $mockedTable = $this->getMockBuilder($name)->onlyMethods(['select'])
            ->disableOriginalConstructor()->getMock();
        $mockedTable->expects($this->any())->method('select')->willReturnCallback(function ($query) use ($dbRows) {
            $resultSetRow = $this->getMockBuilder(ResultSet::class)->onlyMethods(['current'])
                ->disableOriginalConstructor()->getMock();
            $foundEntities = [];
            foreach ($dbRows as $entity) {
                // Loop through select query keys and values and compare those to the object
                foreach ($query as $key => $value) {
                    if ($entity->__get($key) !== $value) {
                        continue 2;
                    }
                }
                $foundEntities[] = $entity;
            }
            $resultSetRow->expects($this->any())->method('current')->willReturn($foundEntities[0] ?? null);
            return $resultSetRow;
        });
        return $mockedTable;
    }
}
