<?php

/**
 * ServerUrlHelper Test Class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Http;

use VuFind\Http\ServerUrlHelper;

/**
 * ServerUrlHelper Test Class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ServerUrlHelperTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ViewTrait;

    /**
     * Specific $_SERVER fields to backup before tests and restore after.
     *
     * @var array
     */
    protected array $serverBackupFields = ['HTTPS', 'HTTP_HOST', 'REQUEST_URI'];

    /**
     * Data provider to return in getBaseUrl tests.
     *
     * @return \Iterator<string, array>
     */
    public static function getBaseUrlProvider(): \Iterator
    {
        yield 'http, no port' => [ '', 'somehost', 'http://somehost' ];
        yield 'http, port' => [ '', 'somehost:8080', 'http://somehost:8080'];
        yield 'https, no port' => [ 'on', 'somehost', 'https://somehost'];
    }

    /**
     * Test helper's getBaseUrl method.
     *
     * @param string $https    $_SERVER['HTTPS'] value
     * @param string $host     $_SERVER['HTTP_HOST'] value
     * @param string $expected Expected result
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getBaseUrlProvider')]
    public function testGetBaseUrl(string $https, string $host, string $expected): void
    {
        $helper = $this->getServerUrlHelper(['HTTPS' => $https, 'HTTP_HOST' => $host]);
        $results = $helper->getBaseUrl();
        $this->assertSame($expected, $results);
    }

    /**
     * Data provider to return in getCurrentUrl and getUrlForPath tests.
     *
     * @return \Iterator<string, array>
     */
    public static function urlsWithPathsProvider(): \Iterator
    {
        yield 'http, no port' => [ '', 'somehost', '/vufind/Record/12345', 'http://somehost/vufind/Record/12345' ];
        yield 'http, port' =>
            [ '', 'somehost:8080', '/vufind/Record/12345', 'http://somehost:8080/vufind/Record/12345'];
        yield 'https, no port' => [ 'on', 'somehost', '/vufind/Record/12345', 'https://somehost/vufind/Record/12345'];
    }

    /**
     * Test helper's getCurrentUrl method.
     *
     * @param string $https       $_SERVER['HTTPS'] value
     * @param string $host        $_SERVER['HTTP_HOST'] value
     * @param string $currentPath Current path
     * @param string $expected    Expected result
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('urlsWithPathsProvider')]
    public function testGetCurrentUrl(string $https, string $host, string $currentPath, string $expected): void
    {
        $helper = $this->getServerUrlHelper(['HTTPS' => $https, 'HTTP_HOST' => $host, 'REQUEST_URI' => $currentPath]);
        $results = $helper->getCurrentUrl();
        $this->assertSame($expected, $results);
    }

    /**
     * Test helper's getUrlForPath method.
     *
     * @param string $https    $_SERVER['HTTPS'] value
     * @param string $host     $_SERVER['HTTP_HOST'] value
     * @param string $path     Input path
     * @param string $expected Expected result
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('urlsWithPathsProvider')]
    public function testGetUrlForPath(string $https, string $host, string $path, string $expected): void
    {
        $helper = $this->getServerUrlHelper(['HTTPS' => $https, 'HTTP_HOST' => $host]);
        $results = $helper->getUrlForPath($path);
        $this->assertSame($expected, $results);
    }

    /**
     * Test setServerContext.
     *
     * @return void
     */
    public function testSetServerContext(): void
    {
        $helper = $this->getServerUrlHelper([]);
        $this->assertSame('http://', $helper->getBaseUrl());
        $helper->setServerContext(['HTTP_HOST' => 'localhost']);
        $this->assertSame('http://localhost', $helper->getBaseUrl());
    }

    /**
     * Return an instance of ServerUrlHelper.
     *
     * @param array $serverContext Server context (like the $_SERVER superglobal)
     *
     * @return ServerUrlHelper
     */
    protected function getServerUrlHelper(array $serverContext): ServerUrlHelper
    {
        return new ServerUrlHelper(false, $serverContext);
    }
}
