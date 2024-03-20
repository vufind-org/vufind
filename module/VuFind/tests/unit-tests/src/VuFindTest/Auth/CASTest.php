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
     * @return Config
     */
    public function getAuthConfig(): Config
    {
        $casConfig = new Config(
            [
                'server' => 'localhost',
                'port' => 1234,
                'context' => 'foo',
                'CACert' => 'bar',
                'login' => 'login',
                'logout' => 'logout',
            ],
            true
        );
        return new Config(['CAS' => $casConfig], true);
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
}
