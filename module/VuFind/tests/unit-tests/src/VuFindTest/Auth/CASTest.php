<?php

/**
 * CAS authentication test class.
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
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Auth;

use Laminas\Config\Config;
use VuFind\Auth\CAS;

/**
 * CAS authentication test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class CASTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Get an authentication object.
     *
     * @param ?Config $config Configuration to use (null for default)
     *
     * @return CAS
     */
    public function getAuthObject(?Config $config = null): CAS
    {
        $obj = new CAS($this->createMock(\VuFind\Auth\ILSAuthenticator::class));
        $obj->setConfig($config ?? $this->getAuthConfig());
        return $obj;
    }

    /**
     * Get a working configuration for the CAS object
     *
     * @param array $extraCasConfig Extra config parameters to include in [CAS] section
     * @param array $extraTopConfig Extra top-level config settings to include
     *
     * @return Config
     */
    public function getAuthConfig(array $extraCasConfig = [], array $extraTopConfig = []): Config
    {
        $casConfig = new Config(
            [
                'server' => 'localhost',
                'port' => 1234,
                'context' => 'foo',
                'CACert' => 'bar',
                'login' => 'http://cas/login',
                'logout' => 'http://cas/logout',
            ] + $extraCasConfig,
            true
        );
        return new Config(['CAS' => $casConfig] + $extraTopConfig, true);
    }

    /**
     * Data provider for testWithMissingConfiguration.
     *
     * @return void
     */
    public static function configKeyProvider(): array
    {
        return [
            'missing server' => ['server'],
            'missing port' => ['port'],
            'missing context' => ['context'],
            'missing CACert' => ['CACert'],
            'missing login' => ['login'],
            'missing logout' => ['logout'],
        ];
    }

    /**
     * Verify that missing configuration keys cause failure.
     *
     * @param string $key Key to omit
     *
     * @return void
     *
     * @dataProvider configKeyProvider
     */
    public function testConfigValidation(string $key): void
    {
        $this->expectException(\VuFind\Exception\Auth::class);

        $config = $this->getAuthConfig();
        unset($config->CAS->$key);
        $this->getAuthObject($config)->getConfig();
    }

    /**
     * Test getSessionInitiator().
     *
     * @return void
     */
    public function testGetSessionInitiator(): void
    {
        $cas = $this->getAuthObject();
        $this->assertEquals(
            'http://cas/login?service=http%3A%2F%2Ffoo%2Fbar%3Fauth_method%3DCAS',
            $cas->getSessionInitiator('http://foo/bar')
        );
    }

    /**
     * Test logout().
     *
     * @return void
     */
    public function testLogout(): void
    {
        $cas = $this->getAuthObject();
        $this->assertEquals(
            'http://cas/logout?service=http%3A%2F%2Ffoo%2Fbar',
            $cas->logout('http://foo/bar')
        );
    }

    /**
     * Test missing service base URL configuration.
     *
     * @return void
     */
    public function testMissingBaseUrlConfig(): void
    {
        $this->expectException(\VuFind\Exception\Auth::class);
        $this->expectExceptionMessage('Valid CAS/service_base_url or Site/url config parameters are required.');
        $cas = $this->getAuthObject();
        $cas->setConfig($this->getAuthConfig());
        $this->callMethod($cas, 'getServiceBaseUrl');
    }

    /**
     * Test working service base URL configuration.
     *
     * @return void
     */
    public function testWorkingBaseUrlConfig(): void
    {
        $cas = $this->getAuthObject();
        $urls = ['http://foo', 'http://bar'];
        $cas->setConfig($this->getAuthConfig(['service_base_url' => $urls]));
        $this->assertEquals($urls, $this->callMethod($cas, 'getServiceBaseUrl'));
    }

    /**
     * Data provider for testBaseUrlConfigFallback.
     *
     * @return void
     */
    public static function fallbackUrlProvider(): array
    {
        return [
            'without port' => ['http://myuniversity.edu/foo/bar', 'http://myuniversity.edu'],
            'with port' => ['https://myuniversity.edu:8080/foo/bar', 'https://myuniversity.edu:8080'],
        ];
    }

    /**
     * Test service base URL configuration fallback to site URL.
     *
     * @param string $url  URL for configuration
     * @param string $host Expected hostname extracted from $url
     *
     * @return void
     *
     * @dataProvider fallbackUrlProvider
     */
    public function testBaseUrlConfigFallback(string $url, string $host): void
    {
        $cas = $this->getAuthObject();
        $config = $this->getAuthConfig([], ['Site' => ['url' => $url]]);
        $cas->setConfig($config);
        $this->assertEquals([$host], $this->callMethod($cas, 'getServiceBaseUrl'));
    }

    /**
     * Test service base URL configuration fallback to invalid site URL.
     *
     * @return void
     */
    public function testBaseUrlConfigInvalidFallback(): void
    {
        $this->expectException(\VuFind\Exception\Auth::class);
        $this->expectExceptionMessage('Valid CAS/service_base_url or Site/url config parameters are required.');
        $cas = $this->getAuthObject();
        $url = 'not-a-url';
        $config = $this->getAuthConfig([], ['Site' => ['url' => $url]]);
        $cas->setConfig($config);
        $this->callMethod($cas, 'getServiceBaseUrl');
    }
}
