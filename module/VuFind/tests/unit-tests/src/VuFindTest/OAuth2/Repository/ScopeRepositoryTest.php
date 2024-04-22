<?php

/**
 * OAuth2 ScopeRepository tests.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\OAuth2\Repository;

use VuFind\OAuth2\Repository\ScopeRepository;

/**
 * OAuth2 ScopeRepository tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ScopeRepositoryTest extends AbstractTokenRepositoryTestCase
{
    /**
     * Data provider for testScopeRepository
     *
     * @return array
     */
    public static function getTestScopeRepositoryData(): array
    {
        return [
            ['openid', 'OpenID', false, false],
            ['id', 'Unique ID', false, false],
            ['phone', 'Phone', false, true],
        ];
    }

    /**
     * Test scope repository
     *
     * @param string $scopeId Scope ID
     * @param string $desc    Expected description
     * @param bool   $hidden  Expected hidden value
     * @param bool   $ils     Expected "ILS Needed" value
     *
     * @dataProvider getTestScopeRepositoryData
     *
     * @return void
     */
    public function testScopeRepository(
        string $scopeId,
        string $desc,
        bool $hidden,
        bool $ils
    ): void {
        $config = [
            'Scopes' => [
                'openid' => [
                    'description' => 'OpenID',
                ],
                'id' => [
                    'description' => 'Unique ID',
                ],
                'phone' => [
                    'description' => 'Phone',
                    'ils' => true,
                ],
            ],
        ];
        $repo = new ScopeRepository($config);

        $scope = $repo->getScopeEntityByIdentifier($scopeId);
        $this->assertEquals($desc, $scope->getDescription());
        $this->assertEquals($hidden, $scope->gethidden());
        $this->assertEquals($ils, $scope->getILSNeeded());

        $scopes = ['openid', 'id', 'phone'];
        $this->assertEquals(
            $scopes,
            $repo->finalizeScopes($scopes, 'AuthCode', $this->createClientEntity())
        );
    }

    /**
     * Test scope repository with invalid id
     *
     * @return void
     */
    public function testScopeRepositoryWithInvalidId(): void
    {
        $config = [
            'Scopes' => [
                'openid' => [
                    'description' => 'OpenID',
                ],
            ],
        ];
        $repo = new ScopeRepository($config);

        $this->assertNull($repo->getScopeEntityByIdentifier('foo'));
    }

    /**
     * Test scope repository with invalid configuration
     *
     * @return void
     */
    public function testInvalidConfig(): void
    {
        $config = [
            'Scopes' => [
                'openid' => [],
            ],
        ];
        $repo = new ScopeRepository($config);

        $this->expectExceptionMessage("OAuth2 scope config missing 'description'");
        $repo->getScopeEntityByIdentifier('openid');
    }
}
