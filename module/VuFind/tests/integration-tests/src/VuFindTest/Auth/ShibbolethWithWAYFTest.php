<?php
/**
 * Shibboleth authentication test class.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2011.
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
 * @link https://vufind.org Main Page
 */
namespace VuFindTest\Auth;

use Laminas\Config\Config;
use VuFind\Auth\ShibbolethWithWAYF;

/**
 * ShibbolethWithWAYF authentication test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Vaclav Rosecky <vaclav.rosecky@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link https://vufind.org Main Page
 */
class ShibbolethWithWAYFTest extends \VuFindTest\Unit\DbTestCase
{
    use \VuFindTest\Unit\UserCreationTrait;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        static::failIfUsersExist();
    }

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        // Give up if we're not running in CI:
        if (!$this->continuousIntegrationRunning()) {
            $this->markTestSkipped('Continuous integration not running.');
            return;
        }
    }

    /**
     * Get an authentication object.
     *
     * @param Config $config Configuration to use (null for default)
     * @param Config $shibConfig Shibboleth configuration to use (null for default)
     *
     * @return ShibbolethWithWAYF
     */
    public function getAuthObject($config = null, $shibConfig = null)
    {
        if (null === $config) {
            $config = $this->getAuthConfig();
        }
        if (null === $shibConfig) {
            $shibConfig = $this->getShibbolethConfig();
        }
        $obj = new ShibbolethWithWAYF(
            $this->createMock(\Laminas\Session\ManagerInterface::class),
            $shibConfig
        );
        $initializer = new \VuFind\ServiceManager\ServiceInitializer();
        $initializer($this->getServiceManager(), $obj);
        $obj->setConfig($config);
        return $obj;
    }

    /**
     * Get a working configuration for the LDAP object
     *
     * @return Config
     */
    public function getAuthConfig()
    {
        $config = new Config(
            [
                'login' => 'http://localhost/Shibboleth.sso/Login',
                'username' => 'username',
                'email' => 'email',
            ], true
        );
        return new Config(['Shibboleth' => $config], true);
    }

    /**
     * Get a working configuration for the Shibboleth object
     *
     * @return Config
     */
    public function getShibbolethConfig()
    {
        $example1 = new Config(
            [
                'entityId' => 'https://idp.example1.org/',
                'username' => 'username',
                'email' => 'email',
                'cat_username' => 'userLibraryId',
            ], true
        );
        return new Config(['example1' => $example1], true);
    }

    /**
     * Support method -- get parameters to log into an account (but allow override of
     * individual parameters so we can test different scenarios).
     *
     * @param array $overrides Associative array of parameters to override.
     *
     * @return \Laminas\Http\Request
     */
    protected function getLoginRequest($overrides = [])
    {
        $server = $overrides + [
            'Shib-Identity-Provider' => 'https://idp.example1.org/',
            'username' => '700',
            'userLibraryId' => '700',
            'mail' => '700@example.org',
        ];
        $request = new \Laminas\Http\PhpEnvironment\Request();
        $request->setServer(new \Laminas\Stdlib\Parameters($server));
        return $request;
    }

    public function testLogin()
    {
        $user = $this->getAuthObject()->authenticate($this->getLoginRequest());
        $this->assertEquals($user->cat_username, 'example1.700');
        $this->assertEquals($user->username, '700');
    }
}
