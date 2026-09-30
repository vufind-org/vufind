<?php

/**
 * Mink Alma webhook test class.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
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
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use Psr\Http\Message\ResponseInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFindTest\Feature\HttpRequestTrait;
use VuFindTest\Feature\LiveDatabaseTrait;

/**
 * Mink Alma webhook test class.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class AlmaTest extends \VuFindTest\Integration\MinkTestCase
{
    use HttpRequestTrait;
    use LiveDatabaseTrait;

    /**
     * Required configuration.
     *
     * @var array
     */
    protected array $configs = [
        'Alma' => [
            'NewUser' => [
                'idType' => 'BARCODE',
            ],
            'Webhook' => [
                'secret' => 'chocolate',
            ],
        ],
        'config' => [
            'Mail' => [
                'testOnly' => true,
            ],
        ],
        'permissions' => [
            'alma.Webhooks' => [
                'permission' => [
                    'access.alma.webhook.user',
                    'access.alma.webhook.challenge',
                ],
                'role' => 'guest',
            ],
        ],
    ];

    /**
     * Standard setup method.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        static::failIfDataExists();
    }

    /**
     * Test webhook with invalid credentials.
     *
     * @return void
     */
    public function testBadCredentials(): void
    {
        $configs = $this->configs;
        $configs['Alma']['Webhook']['secret'] = 'strawberry';
        $this->changeConfigs($configs);
        $url = $this->getVuFindUrl(path: '/Alma/Webhook') . '?challenge=foo';
        $request = json_encode(['action' => 'FOO']);
        $result = $this->httpPost($url, $request);
        $this->assertSame(403, $result->getStatusCode());
        $result = $this->httpPostWithSignature($url, $request);
        $this->assertSame(403, $result->getStatusCode());
    }

    /**
     * Test webhook with unsupported request.
     *
     * @return void
     */
    public function testUnsupportedRequest(): void
    {
        $this->changeConfigs($this->configs);
        $url = $this->getVuFindUrl('/Alma/Webhook') . '?challenge=foo';
        $request = json_encode([
            'action' => 'FOO',
        ]);
        $result = $this->httpPostWithSignature($url, $request, 'application/json');
        $this->assertSame(200, $result->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode(['challenge' => 'foo']), (string)$result->getBody());
    }

    /**
     * Test webhook USER request.
     *
     * @return void
     */
    public function testUserRequest(): void
    {
        $this->changeConfigs($this->configs);
        $url = $this->getVuFindUrl('/Alma/Webhook') . '?challenge=foo';
        $request = json_encode([
            'action' => 'USER',
            'webhook_user' => [
                'method' => 'CREATE',
                'user' => [
                    'primary_id' => 'fooba',
                    'user_identifier' => [
                        [
                            'id_type' => [
                                'value' => 'Other',
                            ],
                            'value' => 'bar',
                        ],
                        [
                            'id_type' => [
                                'value' => 'BARCODE',
                            ],
                            'value' => '123123',
                        ],
                    ],
                    'last_name' => 'Smith',
                ],
            ],
        ]);
        $result = $this->httpPostWithSignature($url, $request, 'application/json');
        $this->assertSame(200, $result->getStatusCode());
        $userService = $this->getDbService(UserServiceInterface::class);
        $user = $userService->getUserByUsername('123123');
        $this->assertIsObject($user);
        $this->assertSame(
            '123123',
            $user->getCatUsername(),
        );
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        static::removeUsers(['123123']);
    }

    /**
     * Perform an HTTP POST operation with coverage awareness and proper Alma signature.
     *
     * @param string $url     Request URL
     * @param mixed  $body    Request body document
     * @param string $type    Request body content type
     * @param float  $timeout Request timeout in seconds
     * @param array  $headers Request http-headers
     *
     * @return ResponseInterface
     */
    protected function httpPostWithSignature(
        $url,
        $body = null,
        $type = 'application/octet-stream',
        $timeout = null,
        array $headers = []
    ): ResponseInterface {
        $headers['X-Exl-Signature'] = base64_encode(
            hash_hmac(
                'sha256',
                $body ?? '',
                'chocolate',
                true
            )
        );
        return $this->getHttpService()->post(
            $url,
            $body,
            $type,
            $timeout,
            array_merge($headers, $this->getExtraVuFindHttpRequestHeaders())
        );
    }
}
