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

use Closure;
use Laminas\View\Helper\ServerUrl;
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
        $helper = $this->getServerUrlHelper();
        $serverBackup = $this->backupAndSetServerFields($https, $host);
        try {
            $results = $helper->getBaseUrl();
            $this->assertSame($expected, $results);
        } finally {
            $this->restoreServerFields($serverBackup);
        }
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
        $helper = $this->getServerUrlHelper();
        $serverBackup = $this->backupAndSetServerFields($https, $host, $currentPath);
        try {
            $results = $helper->getCurrentUrl();
            $this->assertSame($expected, $results);
        } finally {
            $this->restoreServerFields($serverBackup);
        }
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
        $helper = $this->getServerUrlHelper();
        $serverBackup = $this->backupAndSetServerFields($https, $host);
        try {
            $results = $helper->getUrlForPath($path);
            $this->assertSame($expected, $results);
        } finally {
            $this->restoreServerFields($serverBackup);
        }
    }

    /**
     * Backup the specified $_SERVER fields to $serverBackup and write the given
     * temporary values.
     *
     * @param string $https      $_SERVER['HTTPS'] value to use during tests
     * @param string $host       $_SERVER['HTTP_HOST'] value to use during tests
     * @param string $requestUri $_SERVER['REQUEST_URI'] value to use during tests
     *
     * @return array Backup of these fields
     */
    protected function backupAndSetServerFields(
        string $https,
        string $host,
        string $requestUri = '/vufind/current-page'
    ): array {
        $serverBackup = array_map(fn ($field) => $_SERVER[$field] ?? null, $this->serverBackupFields);

        $_SERVER['HTTPS'] = $https;
        $_SERVER['HTTP_HOST'] = $host;
        $_SERVER['REQUEST_URI'] = $requestUri;

        return $serverBackup;
    }

    /**
     * Restore the specified fields into $_SERVER.
     *
     * @param array $serverBackup Original values to restore
     *
     * @return void
     */
    protected function restoreServerFields(array $serverBackup): void
    {
        foreach ($this->serverBackupFields as $field) {
            if ($backupValue = ($serverBackup[$field] ?? null)) {
                $_SERVER[$field] = $backupValue;
            } else {
                unset($_SERVER[$field]);
            }
        }
    }

    /**
     * Return an instance of ServerUrlHelper.
     *
     * @return ServerUrlHelper
     */
    protected function getServerUrlHelper(): ServerUrlHelper
    {
        return new ServerUrlHelper(
            Closure::fromCallable(new ServerUrl())
        );
    }
}
