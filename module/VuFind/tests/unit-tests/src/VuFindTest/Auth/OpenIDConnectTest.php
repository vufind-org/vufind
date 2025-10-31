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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Josef Moravec <josef.moravec@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Auth;

use Laminas\Http\Client\Adapter\Test as TestAdapter;
use Laminas\Http\Exception\InvalidArgumentException;
use Laminas\Http\Response as HttpResponse;
use Laminas\Session\Container as SessionContainer;
use PHPUnit\Framework\MockObject\Exception;
use RuntimeException;
use VuFind\Auth\ILSAuthenticator;
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
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Tested service
     *
     * @var OpenIDConnect
     */
    protected OpenIDConnect $openid;

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
                    'Default' => [
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
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getAttributesMappingsProvider')]
    public function testGetAttributesMappings(array $config, array $results): void
    {
        $authModule = $this->getOpenIDConnectObject($config);
        $this->assertEquals($results, $this->callMethod($authModule, 'getAttributesMappings'));
    }

    /**
     * Test getProviderFromConfig
     *
     * @return void
     */
    public function testGetProviderFromConfig(): void
    {
        $config = [
            'Default' => [
                'url' => 'openidconnect.provider.url',
                'client_id' => 'test_cliend_id',
                'client_secret' => 'test_client_secret',
                'authorization_endpoint' => 'https://openidconnect.provider.url/oauth/authorize',
                'token_endpoint' => 'https://openidconnect.provider.url/oauth/token',
                'userinfo_endpoint' => 'https://openidconnect.provider.url/oauth/userinfo',
                'issuer' => 'https://openidconnect.provider.url',
                'jwks_uri' => 'https://openidconnect.provider.url/oauth/jwks',
            ],
        ];
        $expected = (object)[
            'authorization_endpoint' => 'https://openidconnect.provider.url/oauth/authorize',
            'token_endpoint' => 'https://openidconnect.provider.url/oauth/token',
            'userinfo_endpoint' => 'https://openidconnect.provider.url/oauth/userinfo',
            'issuer' => 'https://openidconnect.provider.url',
            'jwks_uri' => 'https://openidconnect.provider.url/oauth/jwks',
        ];
        $authModule = $this->getOpenIDConnectObject($config);
        $this->assertEquals($expected, $this->callMethod($authModule, 'getProviderFromConfig'));
    }

    /**
     * Test getProvider
     *
     * @return void
     */
    public function testGetProvider(): void
    {
        // Test getting provider info from .well-known endpoint
        $openid = $this->getOpenIDConnectObject();
        $this->mockResponse(['openidconnectprovider.json']);
        $expected = (object)[
            'authorization_endpoint' => 'http://openidconnect.provider.url/oauth/auz/authorize',
            'token_endpoint' => 'http://openidconnect.provider.url/oauth/oauth20/token',
            'userinfo_endpoint' => 'http://openidconnect.provider.url/oauth/userinfo',
            'issuer' => 'http://openidconnect.provider.url',
            'jwks_uri' => 'http://openidconnect.provider.url/oauth/jwks',
            'registration_endpoint' => 'http://openidconnect.provider.url',
            'scopes_supported' => [
                'READ',
                'WRITE',
                'DELETE',
                'openid',
                'scope',
                'profile',
                'email',
                'address',
                'phone',
            ],
            'response_types_supported' => [
                'code',
                'code id_token',
                'code token',
                'code id_token token',
                'token',
                'id_token',
                'id_token token',
            ],
            'grant_types_supported' => [
                'authorization_code',
                'implicit',
                'password',
                'client_credentials',
                'urn:ietf:params:oauth:grant-type:jwt-bearer',
            ],
            'subject_types_supported' => [
                'public',
            ],
            'id_token_signing_alg_values_supported' => [
                'HS256',
                'HS384',
                'HS512',
                'RS256',
                'RS384',
                'RS512',
                'ES256',
                'ES384',
                'ES512',
                'PS256',
                'PS384',
                'PS512',
            ],
            'id_token_encryption_alg_values_supported' => [
                'RSA1_5',
                'RSA-OAEP',
                'RSA-OAEP-256',
                'A128KW',
                'A192KW',
                'A256KW',
                'A128GCMKW',
                'A192GCMKW',
                'A256GCMKW',
                'dir',
            ],
            'id_token_encryption_enc_values_supported' => [
                'A128CBC-HS256',
                'A192CBC-HS384',
                'A256CBC-HS512',
                'A128GCM',
                'A192GCM',
                'A256GCM',
            ],
            'token_endpoint_auth_methods_supported' => [
                'client_secret_post',
                'client_secret_basic',
                'client_secret_jwt',
                'private_key_jwt',
            ],
            'token_endpoint_auth_signing_alg_values_supported' => [
                'HS256',
                'RS256',
            ],
            'claims_parameter_supported' => false,
            'request_parameter_supported' => false,
            'request_uri_parameter_supported' => false,
        ];
        $this->assertEquals($expected, $this->callMethod($openid, 'getProvider'));
        // Test getting provider info from config using fallback when no automatic discovery is available endpoint
        $openid = $this->getOpenIDConnectObject();
        $config = [
            'Default' => [
                'url' => 'openidconnect.provider.url',
                'client_id' => 'test_cliend_id',
                'client_secret' => 'test_client_secret',
                'authorization_endpoint' => 'https://openidconnect.provider.url/oauth/authorize',
                'token_endpoint' => 'https://openidconnect.provider.url/oauth/token',
                'userinfo_endpoint' => 'https://openidconnect.provider.url/oauth/userinfo',
                'issuer' => 'https://openidconnect.provider.url',
                'jwks_uri' => 'https://openidconnect.provider.url/oauth/jwks',
            ],
        ];
        $expected = (object)[
            'authorization_endpoint' => 'https://openidconnect.provider.url/oauth/authorize',
            'token_endpoint' => 'https://openidconnect.provider.url/oauth/token',
            'userinfo_endpoint' => 'https://openidconnect.provider.url/oauth/userinfo',
            'issuer' => 'https://openidconnect.provider.url',
            'jwks_uri' => 'https://openidconnect.provider.url/oauth/jwks',
        ];
        $authModule = $this->getOpenIDConnectObject($config);
        $this->mockResponse(['openidconnectprovider404.json']);
        $this->assertEquals($expected, $this->callMethod($authModule, 'getProvider'));
    }

    /**
     * Get auth module instance
     *
     * @param array $config Configuration
     *
     * @return OpenIDConnect
     */
    protected function getOpenIDConnectObject(array $config = []): OpenIDConnect
    {
        $defaultConfig = [
            'Default' => [
                'url' => 'openidconnect.provider.url',
                'client_id' => 'test_cliend_id',
                'client_secret' => 'test_client_secret',
            ],
        ];
        $session = new SessionContainer();
        $oidcConfig = empty($config) ? $defaultConfig : $config;
        $ilsAuthenticator = $this->getMockILSAuthenticator();
        $this->openid = new OpenIDConnect($session, $oidcConfig, $ilsAuthenticator);
        return $this->openid;
    }

    /**
     * Mock fixture as HTTP client response
     *
     * @param string|array|null $fixture Fixture file
     *
     * @return void
     * @throws InvalidArgumentException Fixture file could not be loaded as HTTP response
     * @throws RuntimeException         Fixture file does not exist
     */
    protected function mockResponse(array $fixture): void
    {
        $adapter = new TestAdapter();
        if (!empty($fixture)) {
            $responseObj = $this->loadResponse($fixture[0]);
            $adapter->setResponse($responseObj);
            array_shift($fixture);
            foreach ($fixture as $f) {
                $responseObj = $this->loadResponse($f);
                $adapter->addResponse($responseObj);
            }
        }

        $service = new \VuFindHttp\HttpService();
        $service->setDefaultAdapter($adapter);
        $this->openid->setHttpService($service);
    }

    /**
     * Load response from file
     *
     * @param string $filename File name of raw HTTP response
     *
     * @return HttpResponse Response object
     *
     * @throws InvalidArgumentException Fixture file could not be loaded as HTTP response
     * @throws RuntimeException         Fixture file does not exist
     */
    protected function loadResponse(string $filename): HttpResponse
    {
        return HttpResponse::fromString(
            $this->getFixture("auth/$filename")
        );
    }

    /**
     * Get a mock ILS authenticator
     *
     * @return ILSAuthenticator
     * @throws Exception
     */
    protected function getMockILSAuthenticator(): ILSAuthenticator
    {
        return $this->createMock(ILSAuthenticator::class);
    }
}
