<?php

/**
 * MultiAuth authentication test class.
 *
 * PHP version 8
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
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Auth;

use Laminas\Config\Config;
use Laminas\ServiceManager\Exception\InvalidServiceException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use VuFind\Auth\MultiAuth;

/**
 * LDAP authentication test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class MultiAuthTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get an authentication object.
     *
     * @param Config $config Configuration to use (null for default)
     *
     * @return MultiAuth
     */
    public function getAuthObject(Config $config = null): MultiAuth
    {
        $container = new \VuFindTest\Container\MockContainer($this);
        $container->set(\VuFind\Log\Logger::class, $this->createMock(\Laminas\Log\LoggerInterface::class));
        $manager = new \VuFind\Auth\PluginManager($container);
        $obj = $manager->get('MultiAuth');
        $obj->setPluginManager($manager);
        $obj->setConfig($config ?? $this->getAuthConfig());
        return $obj;
    }

    /**
     * Get a working configuration for the auth object
     *
     * @return Config
     */
    public function getAuthConfig(): Config
    {
        $config = new Config(
            [
                'method_order' => 'Database,ILS',
            ],
            true
        );
        return new Config(['MultiAuth' => $config], true);
    }

    /**
     * Verify that missing host causes failure.
     *
     * @return void
     */
    public function testWithMissingMethodOrder(): void
    {
        $this->expectException(\VuFind\Exception\Auth::class);
        $this->expectExceptionMessage(
            'One or more MultiAuth parameters are missing. Check your config.ini!'
        );

        $config = $this->getAuthConfig();
        unset($config->MultiAuth->method_order);
        $this->getAuthObject($config)->getConfig();
    }

    /**
     * Support method -- get parameters to log into an account (but allow override of
     * individual parameters so we can test different scenarios).
     *
     * @param array $overrides Associative array of parameters to override.
     *
     * @return \Laminas\Http\Request
     */
    protected function getLoginRequest(array $overrides = []): \Laminas\Http\Request
    {
        $post = $overrides + [
            'username' => 'testuser', 'password' => 'testpass',
        ];
        $request = new \Laminas\Http\Request();
        $request->setPost(new \Laminas\Stdlib\Parameters($post));
        return $request;
    }

    /**
     * Test login with handler configured to load a service which does not exist.
     *
     * @return void
     */
    public function testLoginWithBadService(): void
    {
        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage(
            'A plugin by the name "InappropriateService" was not found in '
            . 'the plugin manager VuFind\Auth\PluginManager'
        );

        $config = $this->getAuthConfig();
        $config->MultiAuth->method_order = 'InappropriateService,Database';

        $request = $this->getLoginRequest();
        $this->getAuthObject($config)->authenticate($request);
    }

    /**
     * Test login with handler configured to load a class which does not conform
     * to the appropriate authentication interface. (We'll use the factory class
     * as an arbitrary inappropriate class).
     *
     * @return void
     */
    public function testLoginWithBadClass(): void
    {
        $this->expectException(InvalidServiceException::class);
        $badClass = \VuFind\Auth\MultiAuthFactory::class;
        $this->expectExceptionMessage(
            'Plugin ' . ltrim($badClass, '\\') . ' does not belong to VuFind\Auth\AbstractBase'
        );

        $config = $this->getAuthConfig();
        $config->MultiAuth->method_order = $badClass . ',Database';

        $request = $this->getLoginRequest();
        $this->getAuthObject($config)->authenticate($request);
    }

    /**
     * Test login with blank username.
     *
     * @return void
     */
    public function testLoginWithBlankUsername(): void
    {
        $this->expectException(\VuFind\Exception\Auth::class);
        $this->expectExceptionMessage('authentication_error_blank');

        $request = $this->getLoginRequest(['username' => '']);
        $this->getAuthObject()->authenticate($request);
    }

    /**
     * Test login with blank password.
     *
     * @return void
     */
    public function testLoginWithBlankPassword(): void
    {
        $this->expectException(\VuFind\Exception\Auth::class);
        $this->expectExceptionMessage('authentication_error_blank');

        $request = $this->getLoginRequest(['password' => '']);
        $this->getAuthObject()->authenticate($request);
    }
}
