<?php

/**
 * OAuth2/OIDC test class.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022-2024.
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
 * @link     https://vufind.org Main Page
 */

declare(strict_types=1);

namespace VuFindTest\Mink;

use function count;

/**
 * OAuth2/OIDC test class.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
final class OAuth2Test extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\DemoDriverTestTrait;
    use \VuFindTest\Feature\HttpRequestTrait;
    use \VuFindTest\Feature\LiveDatabaseTrait;
    use \VuFindTest\Feature\UserCreationTrait;

    /**
     * Whether a key pair has been created
     *
     * @var bool
     */
    protected $opensslKeyPairCreated = false;

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
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->createOpenSSLKeyPair();
    }

    /**
     * Get config.ini override settings for testing ILS functions.
     *
     * @return array
     */
    protected function getConfigIniOverrides(): array
    {
        return [
            'Catalog' => [
                'driver' => 'Demo',
            ],
        ];
    }

    /**
     * Get OAuth2Server.yaml overrides
     *
     * @param string $redirectUri Redirect URI
     *
     * @return array
     */
    protected function getOauth2ConfigOverrides(string $redirectUri): array
    {
        return [
            'Server' => [
                'encryptionKey' => 'encryption!',
                'hashSalt' => 'Need more salt!',
                'keyPermissionChecks' => false,
                'documentationUrl' => 'https://vufind.org/wiki/configuration:oauth2_oidc',
            ],
            'Clients' => [
                'test' => [
                    'name' => 'Integration Test',
                    'redirectUri' => $redirectUri,
                    'isConfidential' => true,
                    'secret' => password_hash('mysecret', PASSWORD_DEFAULT),
                ],
                'test_limited' => [
                    'name' => 'Integration Test',
                    'redirectUri' => $redirectUri,
                    'isConfidential' => true,
                    'secret' => password_hash('mysecret', PASSWORD_DEFAULT),
                    'allowedScopes' => [
                        'openid',
                        'profile',
                    ],
                ],
            ],
        ];
    }

    /**
     * Set up a test
     *
     * @param string $redirectUri Redirect URI
     *
     * @return void
     */
    protected function setUpTest(string $redirectUri): void
    {
        $this->changeConfigs(
            [
                'config' => $this->getConfigIniOverrides(),
                'Demo' => $this->getDemoIniOverrides(),
            ]
        );

        $this->changeYamlConfigs(
            ['OAuth2Server' => $this->getOauth2ConfigOverrides($redirectUri)]
        );
    }

    /**
     * Data provider for testOAuth2Authorization
     *
     * @return array
     */
    public static function oauth2AuthorizationProvider(): array
    {
        return [
            'test client' => [
                'test',
                [
                    'Read your user identifier',
                    'Read your basic profile information (name, language, birthdate)',
                    'Read a unique hash based on your library user identifier',
                    'Read your age',
                ],
                false,
            ],
            'limited test client' => [
                'test_limited',
                [
                    'Read your user identifier',
                    'Read your basic profile information (name, language, birthdate)',
                ],
                true,
            ],
        ];
    }

    /**
     * Test OAuth2 authorization.
     *
     * @param string $clientId            Client ID
     * @param array  $expectedPermissions Expected permissions in the request
     * @param bool   $limited             Whether the permission set has been limited by the server
     *
     * @return void
     *
     * @dataProvider oauth2AuthorizationProvider
     */
    public function testOAuth2Authorization(string $clientId, array $expectedPermissions, bool $limited): void
    {
        // Bogus redirect URI, but it doesn't matter since the page won't handle the
        // authorization response:
        $redirectUri = $this->getVuFindUrl() . '/Content/faq';
        $this->setUpTest($redirectUri);

        static::removeUsers(['username1']);

        $nonce = time();
        $state = md5((string)$nonce);

        // Go to OAuth2 authorization screen:
        $params = [
            'client_id' => $clientId,
            'scope' => 'openid profile library_user_id age',
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'nonce' => $nonce,
            'state' => $state,
        ];
        $session = $this->getMinkSession();
        $session->visit(
            $this->getVuFindUrl() . '/OAuth2/Authorize?' . http_build_query($params)
        );
        $page = $session->getPage();

        // Set up user account:
        $this->clickCss($page, '.createAccountLink');
        $this->fillInAccountForm($page);
        $this->clickCss($page, 'input.btn.btn-primary');

        // Link ILS profile:
        $this->submitCatalogLoginForm($page, 'catuser', 'catpass');
        // Create a hash like UserEntity does:
        $oauth2ConfigOverrides = $this->getOauth2ConfigOverrides($redirectUri);
        $catIdHash = hash(
            'sha256',
            'catuser' . $oauth2ConfigOverrides['Server']['hashSalt']
        );

        foreach ($expectedPermissions as $index => $permission) {
            $this->assertEquals(
                $permission,
                $this->findCssAndGetText($page, 'div.oauth2-prompt li', null, $index)
            );
        }
        // Ensure that there are no more permissions:
        $this->unFindCss($page, 'div.oauth2-prompt li', null, count($expectedPermissions));

        $this->clickCss($page, '.form-oauth2-authorize button.btn.btn-primary');

        $this->waitForPageLoad($page);
        [$host] = explode('?', $session->getCurrentUrl());
        $this->assertEquals($redirectUri, $host);

        parse_str(parse_url($session->getCurrentUrl(), PHP_URL_QUERY), $queryParams);
        $this->assertArrayHasKey('code', $queryParams);
        $this->assertArrayHasKey('state', $queryParams);
        $this->assertEquals($state, $queryParams['state']);

        // Fetch and check idToken with back-channel requests:
        $tokenParams = [
            'code' => $queryParams['code'],
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => 'mysecret',
        ];
        $response = $this->httpPost(
            $this->getVuFindUrl() . '/OAuth2/token',
            http_build_query($tokenParams),
            'application/x-www-form-urlencoded'
        );

        $this->assertEquals(200, $response->getStatusCode());
        $tokenResult = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('id_token', $tokenResult);
        $this->assertArrayHasKey('token_type', $tokenResult);

        // Fetch public key to verify idToken:
        $response = $this->httpGet($this->getVuFindUrl() . '/OAuth2/jwks');
        $this->assertEquals(
            200,
            $response->getStatusCode(),
            'Response: ' . $response->getContent()
        );
        $jwks = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('n', $jwks['keys'][0] ?? []);

        $idToken = \Firebase\JWT\JWT::decode(
            $tokenResult['id_token'],
            \Firebase\JWT\JWK::parseKey($jwks['keys'][0], 'RS256')
        );

        $this->assertInstanceOf(\stdClass::class, $idToken);
        $this->assertEquals($clientId, $idToken->aud);
        $this->assertEquals($nonce, $idToken->nonce);
        $this->assertEquals('Tester McTestenson', $idToken->name);
        $this->assertEquals('Tester', $idToken->given_name);
        $this->assertEquals('McTestenson', $idToken->family_name);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}$/',
            $idToken->birthdate
        );
        if ($limited) {
            $this->assertObjectNotHasProperty('library_user_id', $idToken);
            $this->assertObjectNotHasProperty('age', $idToken);
        } else {
            $this->assertEquals($catIdHash, $idToken->library_user_id);
            $this->assertEquals(
                \DateTime::createFromFormat('Y-m-d', $idToken->birthdate)
                    ->diff(new \DateTimeImmutable())->format('%y'),
                $idToken->age
            );
        }

        // Test the userinfo endpoint:
        $response = $this->httpGet(
            $this->getVuFindUrl() . '/OAuth2/userinfo',
            [],
            '',
            [
                'Authorization' => $tokenResult['token_type'] . ' '
                . $tokenResult['access_token'],
            ]
        );
        $this->assertEquals(
            200,
            $response->getStatusCode(),
            'Response: ' . $response->getContent()
        );

        $userInfo = json_decode($response->getBody(), true);
        $this->assertEquals($idToken->sub, $userInfo['sub']);
        $this->assertEquals($nonce, $userInfo['nonce']);
        $this->assertEquals('Tester McTestenson', $userInfo['name']);
        $this->assertEquals('Tester', $userInfo['given_name']);
        $this->assertEquals('McTestenson', $userInfo['family_name']);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}$/',
            $userInfo['birthdate']
        );
        if ($limited) {
            $this->assertObjectNotHasProperty('library_user_id', $idToken);
            $this->assertObjectNotHasProperty('age', $idToken);
        } else {
            $this->assertEquals($catIdHash, $userInfo['library_user_id']);
            $this->assertEquals(
                \DateTime::createFromFormat('Y-m-d', $userInfo['birthdate'])
                    ->diff(new \DateTimeImmutable())->format('%y'),
                $userInfo['age']
            );
        }

        // Test token request with bad credentials:
        $tokenParams['client_secret'] = 'badsecret';
        $response = $this->httpPost(
            $this->getVuFindUrl() . '/OAuth2/token',
            http_build_query($tokenParams),
            'application/x-www-form-urlencoded'
        );
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals(
            401,
            $response->getStatusCode(),
            'Response: ' . $response->getContent()
        );
        $tokenResult = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('error', $tokenResult);
        $this->assertEquals('invalid_client', $tokenResult['error']);
    }

    /**
     * Test OAuth2 denied authorization.
     *
     * @return void
     */
    public function testOAuth2Unauthorized(): void
    {
        // Bogus redirect URI, but it doesn't matter since the page won't handle the
        // authorization response:
        $redirectUri = $this->getVuFindUrl() . '/Content/faq';
        $this->setUpTest($redirectUri);

        $nonce = time();
        $state = md5((string)$nonce);

        // Go to OAuth2 authorization screen:
        $params = [
            'client_id' => 'test',
            'scope' => 'openid profile library_user_id',
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'nonce' => $nonce,
            'state' => $state,
        ];
        $session = $this->getMinkSession();
        $session->visit(
            $this->getVuFindUrl() . '/OAuth2/Authorize?' . http_build_query($params)
        );
        $page = $session->getPage();

        // Log in:
        $this->fillInLoginForm($page, 'username1', 'test', false);
        $this->submitLoginForm($page, false);

        // Deny authorization:
        $this->clickCss($page, '.form-oauth2-authorize button.btn.btn-default');

        [$host] = explode('?', $session->getCurrentUrl());
        $this->assertEquals($redirectUri, $host);

        parse_str(parse_url($session->getCurrentUrl(), PHP_URL_QUERY), $queryParams);
        $this->assertArrayHasKey('error', $queryParams);
        $this->assertArrayHasKey('state', $queryParams);
        $this->assertEquals($state, $queryParams['state']);
        $this->assertEquals('access_denied', $queryParams['error']);
    }

    /**
     * Test OAuth2 authorization with invalid scope.
     *
     * @return void
     */
    public function testOAuth2InvalidScope(): void
    {
        // Bogus redirect URI, but it doesn't matter since the page won't handle the
        // authorization response:
        $redirectUri = $this->getVuFindUrl() . '/Content/faq';
        $this->setUpTest($redirectUri);

        $nonce = time();
        $state = md5((string)$nonce);

        // Go to OAuth2 authorization screen:
        $params = [
            'client_id' => 'test',
            'scope' => 'openid foo',
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'nonce' => $nonce,
            'state' => $state,
        ];
        $session = $this->getMinkSession();
        $session->visit(
            $this->getVuFindUrl() . '/OAuth2/Authorize?' . http_build_query($params)
        );
        $page = $session->getPage();

        // Log in:
        $this->fillInLoginForm($page, 'username1', 'test', false);
        $this->submitLoginForm($page, false);

        [$host] = explode('?', $session->getCurrentUrl());
        $this->assertEquals($redirectUri, $host);

        parse_str(parse_url($session->getCurrentUrl(), PHP_URL_QUERY), $queryParams);
        $this->assertArrayHasKey('error', $queryParams);
        $this->assertEquals('invalid_scope', $queryParams['error']);
    }

    /**
     * Test OAuth2 authorization with invalid client.
     *
     * @return void
     */
    public function testOAuth2InvalidClient(): void
    {
        // Disable logging of a known exception:
        $this->changeConfigs(
            [
                'config' => [
                    'Logging' => [
                        'file' => null,
                    ],
                ],
            ]
        );
        // Bogus redirect URI, but it doesn't matter since the page won't handle the
        // authorization response:
        $redirectUri = $this->getVuFindUrl() . '/Content/faq';
        $this->setUpTest($redirectUri);

        $nonce = time();
        $state = md5((string)$nonce);

        // Go to OAuth2 authorization screen:
        $params = [
            'client_id' => 'foo',
            'scope' => 'openid profile',
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'nonce' => $nonce,
            'state' => $state,
        ];
        $session = $this->getMinkSession();
        // We expect an error, so let's act like production mode for realistic testing:
        $session->setWhoopsDisabled(true);
        $session->visit(
            $this->getVuFindUrl() . '/OAuth2/Authorize?' . http_build_query($params)
        );
        $page = $session->getPage();

        $this->assertEquals(
            'An error has occurred',
            $this->findCssAndGetText($page, '.alert-danger p')
        );
    }

    /**
     * Test OpenID Connect Discovery.
     *
     * @return void
     */
    public function testOIDCDiscovery(): void
    {
        // Bogus redirect URI, but it doesn't matter since the page won't handle the
        // authorization response:
        $baseUrl = $this->getVuFindUrl();
        $this->setUpTest('');

        $urlData = parse_url($this->getVuFindUrl());
        // Issuer is always https:
        $issuer = 'https://' . $urlData['host'];
        if ($port = $urlData['port'] ?? null) {
            $issuer .= ":$port";
        }
        $expected = [
            'issuer' => $issuer,
            'authorization_endpoint' => "$baseUrl/OAuth2/Authorize",
            'token_endpoint' => "$baseUrl/OAuth2/Token",
            'token_endpoint_auth_methods_supported' => [
                'client_secret_post',
                'client_secret_basic',
            ],
            'userinfo_endpoint' => "$baseUrl/OAuth2/UserInfo",
            'jwks_uri' => "$baseUrl/OAuth2/jwks",
            'response_types_supported' => ['code'],
            'scopes_supported' => [
                'openid',
                'username',
                'cat_id',
                'address',
                'email',
                'phone',
                'profile',
                'id',
                'name',
                'age',
                'birthdate',
                'locale',
                'block_status',
                'library_user_id',
            ],
            'grant_types_supported' => ['authorization_code'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'service_documentation' => 'https://vufind.org/wiki/configuration:oauth2_oidc',
        ];

        $response = $this->httpGet($this->getVuFindUrl() . '/.well-known/openid-configuration');
        $this->assertEquals(
            'application/json',
            $response->getHeaders()->get('Content-Type')->getFieldValue()
        );
        $json = $response->getBody();
        $this->assertJsonStringEqualsJsonString(json_encode($expected), $json);
    }

    /**
     * Create a public/private key pair
     *
     * @return void
     */
    protected function createOpenSSLKeyPair(): void
    {
        $privateKeyPath = $this->pathResolver->getLocalConfigPath(
            'oauth2_private.key',
            null,
            true
        );
        $publicKeyPath = $this->pathResolver->getLocalConfigPath(
            'oauth2_public.key',
            null,
            true
        );

        // Creates backups if the files exists:
        if (file_exists($privateKeyPath)) {
            if (!copy($privateKeyPath, "$privateKeyPath.bak")) {
                throw new \Exception(
                    "Could not copy $privateKeyPath to $privateKeyPath.bak"
                );
            }
        }
        if (file_exists($publicKeyPath)) {
            if (!copy($publicKeyPath, "$publicKeyPath.bak")) {
                throw new \Exception(
                    "Could not copy $publicKeyPath to $publicKeyPath.bak"
                );
            }
        }

        $privateKey = openssl_pkey_new(
            [
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]
        );
        if (!$privateKey) {
            throw new \Exception(
                'Could not create private key: ' . openssl_error_string()
            );
        }

        if (!openssl_pkey_export_to_file($privateKey, $privateKeyPath)) {
            throw new \Exception(
                "Could not write private key $privateKeyPath: "
                . openssl_error_string()
            );
        }

        // Generate the public key:
        $details = openssl_pkey_get_details($privateKey);
        if (!$details) {
            throw new \Exception(
                'Could not get private key details: ' . openssl_error_string()
            );
        }
        if (!file_put_contents($publicKeyPath, $details['key'])) {
            throw new \Exception("Could not write public key $publicKeyPath");
        }

        $this->opensslKeyPairCreated = true;
    }

    /**
     * Restore any previous public/private key pair
     *
     * @return void
     */
    protected function restoreOpenSSLKeyPair(): void
    {
        $paths = [
            $this->pathResolver
                ->getLocalConfigPath('oauth2_private.key', null, true),
            $this->pathResolver
                ->getLocalConfigPath('oauth2_public.key', null, true),
        ];

        foreach ($paths as $path) {
            if (file_exists("$path.bak")) {
                copy("$path.bak", $path);
            } else {
                unlink($path);
            }
        }
        $this->opensslKeyPairCreated = false;
    }

    /**
     * Restore configurations to the state they were in prior to a call to
     * changeConfig().
     *
     * @return void
     */
    protected function restoreConfigs()
    {
        parent::restoreConfigs();
        if ($this->opensslKeyPairCreated) {
            $this->restoreOpenSSLKeyPair();
        }
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        static::removeUsers(['username1']);
    }
}
