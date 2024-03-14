<?php

/**
 * OpenID Connect test.
 *
 * PHP version 8
 *
 * Copyright (C) Moravian Library 2024.
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
 * @author   Josef Moravec <josef.moravec@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Auth;

use Laminas\Config\Config;
use Laminas\Session\Container as SessionContainer;
use VuFind\Auth\OpenIDConnect;

/**
 * OpenID Connect test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Josef Moravec <josef.moravec@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class OpenIDConnectTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * GetAttributeMappings test data provider
     *
     * @return array
     */
    public static function getAttributesMappingsProvider(): array
    {
        return [
            'User configured attributes' => [
                [
                    'OpenIDConnect' => [
                        'url' => 'openidconnect.provider.url',
                        'client_id' => 'test_cliend_id',
                        'client_secret' => 'test_client_secret',
                        'attributes' => [
                            'firstname' => 'test_given_name',
                            'lastname' => 'test_family_name',
                            'email' => 'test_email',
                        ],
                    ],
                ],
                [
                    'firstname' => 'test_given_name',
                    'lastname' => 'test_family_name',
                    'email' => 'test_email',
                ],
            ],
            'Default attributes' => [
                [],
                [
                    'firstname' => 'given_name',
                    'lastname' => 'family_name',
                    'email' => 'email',
                ],
            ],
        ];
    }

    /**
     * Test GetAttributeMappings
     *
     * @param array $config  Auth module configuration
     * @param array $results Expected mappings
     *
     * @dataProvider getAttributesMappingsProvider
     *
     * @return void
     */
    public function testGetAttributesMappings(array $config, array $results): void
    {
        $authModule = $this->getOpenIDConnectObject($config);
        $this->assertEquals($results, $this->callMethod($authModule, 'getAttributesMappings'));
    }

    /**
     * Get auth module instance
     *
     * @param array $config Configuration
     *
     * @return OpenIDConnect
     */
    protected function getOpenIDConnectObject(array $config): OpenIDConnect
    {
        $defaultConfig = [
            'OpenIDConnect' => [
                'url' => 'openidconnect.provider.url',
                'client_id' => 'test_cliend_id',
                'client_secret' => 'test_client_secret',
            ],
        ];
        $session = new SessionContainer();
        $oidcConfig = new Config(empty($config) ? $defaultConfig : $config);
        return new OpenIDConnect($session, $oidcConfig);
    }
}
