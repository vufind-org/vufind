<?php

/**
 * Database authentication test class.
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

use VuFind\Auth\Database;

/**
 * Database authentication test class.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
final class DatabaseTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\LiveDatabaseTrait;
    use \VuFindTest\Feature\LiveDetectionTrait;

    /**
     * Object to test
     *
     * @var Database
     */
    protected $auth;

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
        // Give up if we're not running in CI:
        if (!$this->continuousIntegrationRunning()) {
            $this->markTestSkipped('Continuous integration not running.');
            return;
        }
        $this->auth = new Database();
        $this->auth->setDbServiceManager($this->getLiveDbServiceManager());
    }

    /**
     * Test account creation is allowed.
     *
     * @return void
     */
    public function testCreateIsAllowed()
    {
        $this->assertTrue($this->auth->supportsCreation());
    }

    /**
     * Support method -- turn an array into a request populated for use by the
     * authentication class.
     *
     * @param array $post Associative array of POST parameters.
     *
     * @return \Laminas\Http\Request
     */
    protected function getRequest($post)
    {
        $request = new \Laminas\Http\Request();
        $request->setPost(new \Laminas\Stdlib\Parameters($post));
        return $request;
    }

    /**
     * Support method -- get parameters to create an account (but allow override of
     * individual parameters so we can test different scenarios).
     *
     * @param array $overrides Associative array of parameters to override.
     *
     * @return \Laminas\Http\Request
     */
    protected function getAccountCreationRequest($overrides = [])
    {
        $post = $overrides + [
            'username' => 'testuser', 'email' => 'user@test.com',
            'password' => 'testpass', 'password2' => 'testpass',
            'firstname' => 'Test', 'lastname' => 'User',
        ];
        return $this->getRequest($post);
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
        $post = $overrides + [
            'username' => 'testuser', 'password' => 'testpass',
        ];
        return $this->getRequest($post);
    }

    /**
     * Test blank username.
     *
     * @return void
     */
    public function testCreationWithBlankUsername()
    {
        $this->expectException(\VuFind\Exception\Auth::class);

        $request = $this->getAccountCreationRequest(['username' => '']);
        $this->auth->create($request);
    }

    /**
     * Test blank password.
     *
     * @return void
     */
    public function testCreationWithBlankPassword()
    {
        $this->expectException(\VuFind\Exception\Auth::class);

        $request = $this->getAccountCreationRequest(['password' => '']);
        $this->auth->create($request);
    }

    /**
     * Test password mismatch.
     *
     * @return void
     */
    public function testCreationWithPasswordMismatch()
    {
        $this->expectException(\VuFind\Exception\Auth::class);

        $request = $this->getAccountCreationRequest(['password2' => '']);
        $this->auth->create($request);
    }

    /**
     * Test invalid email.
     *
     * @return void
     */
    public function testCreationWithInvalidEmail()
    {
        $this->expectException(\VuFind\Exception\Auth::class);

        $request = $this->getAccountCreationRequest(['email' => 'garbage']);
        $this->auth->create($request);
    }

    /**
     * Test successful account creation.
     *
     * @return void
     */
    public function testSuccessfulCreation()
    {
        $request = $this->getAccountCreationRequest();
        $newUser = $this->auth->create($request)->toArray();
        foreach ($request->getPost() as $key => $value) {
            // Skip the password confirmation value!
            if ($key !== 'password2') {
                $this->assertEquals($value, $newUser[$key]);
            }
        }
    }

    /**
     * Test duplicate username.
     *
     * @return void
     */
    public function testCreationWithDuplicateUsername()
    {
        $this->expectException(\VuFind\Exception\Auth::class);

        $request = $this->getAccountCreationRequest(['email' => 'user2@test.com']);
        $this->auth->create($request);
    }

    /**
     * Test duplicate email.
     *
     * @return void
     */
    public function testCreationWithDuplicateEmail()
    {
        $this->expectException(\VuFind\Exception\Auth::class);

        $request = $this->getAccountCreationRequest(['username' => 'testuser2']);
        $this->auth->create($request);
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
        $this->auth->authenticate($request);
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
        $this->auth->authenticate($request);
    }

    /**
     * Test login with unknown username.
     *
     * @return void
     */
    public function testLoginWithUnrecognizedUsername()
    {
        $this->expectException(\VuFind\Exception\Auth::class);

        $request = $this->getLoginRequest(['username' => 'unknown']);
        $this->auth->authenticate($request);
    }

    /**
     * Test login with bad password.
     *
     * @return void
     */
    public function testLoginWithBadPassword()
    {
        $this->expectException(\VuFind\Exception\Auth::class);

        $request = $this->getLoginRequest(['password' => "' OR 1=1 LIMIT 1"]);
        $this->auth->authenticate($request);
    }

    /**
     * Test successful login.
     *
     * @return void
     */
    public function testLogin()
    {
        $user = $this->auth->authenticate($this->getLoginRequest());
        $this->assertEquals('testuser', $user->getUsername());
        $this->assertEquals('user@test.com', $user->getEmail());
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        static::removeUsers('testuser');
    }
}
