<?php
/**
 * OAuth2 ClientRepository tests.
 *
 * PHP version 7
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

use VuFind\OAuth2\Entity\ClientEntity;
use VuFind\OAuth2\Repository\ClientRepository;

/**
 * OAuth2 ClientRepository tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ClientRepositoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test client repository
     *
     * @return void
     */
    public function testClientRepository(): void
    {
        $config = [
            'Clients' => [
                'openid_test' => [
                    'name' => 'OpenID Tester',
                    'redirectUri' => 'http://localhost/callback',
                ],
                'confidential' => [
                    'name' => 'Confidential Client',
                    'secret' => password_hash('password', PASSWORD_DEFAULT),
                    'redirectUri' => 'http://localhost/secure',
                    'isConfidential' => true,
                ],
            ]
        ];
        $repo = new ClientRepository($config);
        $this->assertFalse(
            $repo->validateClient(
                'foo',
                'bar',
                null
            )
        );
        $this->assertTrue(
            $repo->validateClient(
                'openid_test',
                password_hash('invalid', PASSWORD_DEFAULT),
                null
            )
        );
        $this->assertTrue(
            $repo->validateClient(
                'openid_test',
                password_hash('supersecret', PASSWORD_DEFAULT),
                null
            )
        );
        $this->assertFalse(
            $repo->validateClient(
                'confidential',
                password_hash('invalid', PASSWORD_DEFAULT),
                null
            )
        );
        $this->assertTrue(
            $repo->validateClient(
                'confidential',
                'password',
                null
            )
        );

        $this->assertNull($repo->getClientEntity('foo'));

        $client = $repo->getClientEntity('openid_test');
        $this->assertInstanceOf(ClientEntity::class, $client);
        $this->assertEquals('OpenID Tester', $client->getName());
        $this->assertEquals('http://localhost/callback', $client->getRedirectUri());
        $this->assertFalse($client->isConfidential());

        $client = $repo->getClientEntity('confidential');
        $this->assertInstanceOf(ClientEntity::class, $client);
        $this->assertEquals('Confidential Client', $client->getName());
        $this->assertEquals('http://localhost/secure', $client->getRedirectUri());
        $this->assertTrue($client->isConfidential());
    }
}
