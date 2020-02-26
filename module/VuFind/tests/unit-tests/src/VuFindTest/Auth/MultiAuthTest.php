<?php
/**
 * MultiAuth authentication test class.
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
 * @link     https://vufind.org Main Page
 */
namespace VuFindTest\Auth;

use Zend\Config\Config;

/**
 * LDAP authentication test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class MultiAuthTest extends \VuFindTest\Unit\DbTestCase
{
    /**
     * Get an authentication object.
     *
     * @param Config $config Configuration to use (null for default)
     *
     * @return LDAP
     */
    public function getAuthObject($config = null)
    {
        if (null === $config) {
            $config = $this->getAuthConfig();
        }
        $manager = $this->getAuthManager();
        $obj = clone $manager->get('MultiAuth');
        $obj->setPluginManager($manager);
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
                'method_order' => 'Database,ILS'
            ], true
        );
        return new Config(['MultiAuth' => $config], true);
    }

    /**
     * Verify that missing host causes failure.
     *
     * @return void
     */
    public function testWithMissingMethodOrder()
    {
        $this->expectException(\VuFind\Exception\Auth::class);

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
     * @return \Zend\Http\Request
     */
    protected function getLoginRequest($overrides = [])
    {
        $post = $overrides + [
            'username' => 'testuser', 'password' => 'testpass'
        ];
        $request = new \Zend\Http\Request();
        $request->setPost(new \Zend\Stdlib\Parameters($post));
        return $request;
    }

    /**
     * Test login with handler configured to load a service which does not exist.
     *
     * @return void
     */
    public function testLoginWithBadService()
    {
        $this->expectException(\Zend\ServiceManager\Exception\ServiceNotFoundException::class);

        $config = $this->getAuthConfig();
        $config->MultiAuth->method_order = 'InappropriateService,Database';

        $request = $this->getLoginRequest();
        $this->getAuthObject($config)->authenticate($request);
    }

    /**
     * Test login with handler configured to load a class which does not conform
     * to the appropriate authentication interface.  (We'll use this test class
     * as an arbitrary inappropriate class).
     *
     * @return void
     */
    public function testLoginWithBadClass()
    {
        $this->expectException(\Zend\ServiceManager\Exception\InvalidServiceException::class);

        $config = $this->getAuthConfig();
        $config->MultiAuth->method_order = get_class($this) . ',Database';

        $request = $this->getLoginRequest();
        $this->getAuthObject($config)->authenticate($request);
    }

    /**
     * Test login with blank username.
     *
     * @return void
     */
    public function testLoginWithBlankUsername()
    {
        $this->expectException(\VuFind\Exception\Auth::class);

        $request = $this->getLoginRequest(['username' => '']);
        $this->getAuthObject()->authenticate($request);
    }

    /**
     * Test login with blank password.
     *
     * @return void
     */
    public function testLoginWithBlankPassword()
    {
        $this->expectException(\VuFind\Exception\Auth::class);

        $request = $this->getLoginRequest(['password' => '']);
        $this->getAuthObject()->authenticate($request);
    }
}
