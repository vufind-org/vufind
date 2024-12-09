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

use Laminas\Config\Config;
use Laminas\Stdlib\Parameters;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Auth\Database;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\Http\PhpEnvironment\Request;

/**
 * Database authentication test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class DatabaseUnitTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test validation of empty create request.
     *
     * @return void
     */
    public function testEmptyCreateRequest(): void
    {
        $this->expectException(\VuFind\Exception\Auth::class);
        $this->expectExceptionMessage('Username cannot be blank');

        $db = new Database();
        $db->create($this->getRequest());
    }

    /**
     * Test validation of create request w/blank password.
     *
     * @return void
     */
    public function testEmptyPasswordCreateRequest(): void
    {
        $this->expectException(\VuFind\Exception\Auth::class);
        $this->expectExceptionMessage('Password cannot be blank');

        $db = new Database();
        $arr = $this->getCreateParams();
        $arr['password'] = $arr['password2'] = '';
        $db->create($this->getRequest($arr));
    }

    /**
     * Test validation of create request w/mismatched passwords.
     *
     * @return void
     */
    public function testMismatchedPasswordCreateRequest(): void
    {
        $this->expectException(\VuFind\Exception\Auth::class);
        $this->expectExceptionMessage('Passwords do not match');

        $db = new Database();
        $arr = $this->getCreateParams();
        $arr['password2'] = 'bad';
        $db->create($this->getRequest($arr));
    }

    /**
     * Data provider for testCreateWithPasswordPolicy
     *
     * @return array
     */
    public static function getTestCreateWithPasswordPolicyData(): array
    {
        $numericConfig = [
            'minimum_password_length' => 4,
            'maximum_password_length' => 5,
            'password_pattern' => 'numeric',
        ];
        $alnumConfig = [
            'minimum_password_length' => 4,
            'maximum_password_length' => 5,
            'password_pattern' => 'alphanumeric',
        ];
        $patternConfig = [
            'minimum_password_length' => 4,
            'maximum_password_length' => 5,
            'password_pattern' => '([\p{L}\p{N}]+)',
        ];
        return [
            // Numeric:
            [
                $numericConfig,
                '123',
                \VuFind\Exception\Auth::class,
                'password_minimum_length',
            ],
            [
                $numericConfig,
                '123456',
                \VuFind\Exception\Auth::class,
                'password_maximum_length',
            ],
            [
                $numericConfig,
                'pass',
                \VuFind\Exception\Auth::class,
                'password_error_invalid',
            ],
            [
                $numericConfig,
                '1234',
                \Exception::class,
                'Service manager missing', // == success
            ],
            [
                $numericConfig,
                '12345',
                \Exception::class,
                'Service manager missing', // == success
            ],

            // Alphanumeric:
            [
                $alnumConfig,
                '1ab',
                \VuFind\Exception\Auth::class,
                'password_minimum_length',
            ],
            [
                $alnumConfig,
                '1abcde',
                \VuFind\Exception\Auth::class,
                'password_maximum_length',
            ],
            [
                $alnumConfig,
                'pass!',
                \VuFind\Exception\Auth::class,
                'password_error_invalid',
            ],
            [
                $alnumConfig,
                '1abc',
                \Exception::class,
                'Service manager missing', // == success
            ],
            [
                $alnumConfig,
                '1abcd',
                \Exception::class,
                'Service manager missing', // == success
            ],

            // Pattern:
            [
                $patternConfig,
                '1abc!',
                \VuFind\Exception\Auth::class,
                'password_error_invalid',
            ],
            [
                $patternConfig,
                'abd/e',
                \VuFind\Exception\Auth::class,
                'password_error_invalid',
            ],
            [
                $patternConfig,
                '1abcÖ',
                \Exception::class,
                'Service manager missing', // == success
            ],
            [
                $patternConfig,
                'abcδ',
                \Exception::class,
                'Service manager missing', // == success
            ],
        ];
    }

    /**
     * Test validation of create request with a password policy.
     *
     * @param array  $authConfig             Authentication configuration
     * @param string $password               Password for test
     * @param string $expectedExceptionClass Expected exception class
     * @param string $expectedExceptionMsg   Expected exception message
     *
     * @dataProvider getTestCreateWithPasswordPolicyData
     *
     * @return void
     */
    public function testCreateWithPasswordPolicy(
        array $authConfig,
        string $password,
        string $expectedExceptionClass,
        string $expectedExceptionMsg
    ): void {
        $config = new Config(
            [
                'Authentication' => $authConfig,
            ]
        );
        $db = new Database();
        $db->setConfig($config);
        $arr = $this->getCreateParams();
        $arr['password'] = $password;
        $arr['password2'] = $password;
        $this->expectException($expectedExceptionClass);
        $this->expectExceptionMessage($expectedExceptionMsg);
        $db->create($this->getRequest($arr));
    }

    /**
     * Test validation of create request with a password policy.
     *
     * @return void
     */
    public function testCreateWithBadPasswordPolicyPattern(): void
    {
        $config = new Config(
            [
                'Authentication' => [
                    'password_pattern' => 'a/',
                ],
            ]
        );
        $db = new Database();
        $db->setConfig($config);
        $arr = $this->getCreateParams();
        $arr['password'] = 'abcδ';
        $arr['password2'] = 'abcδ';
        $this->expectExceptionMessage('Invalid regexp in password pattern: a/');
        $db->create($this->getRequest($arr));
    }

    /**
     * Data provider for testCreateWithUsernamePolicy
     *
     * @return array
     */
    public static function getTestCreateWithUsernamePolicyData(): array
    {
        $defaultConfig = [
            'username_pattern' => '([\\x21\\x23-\\x2B\\x2D-\\x2F\\x3D\\x3F\\x40'
            . '\\x5E-\\x60\\x7B-\\x7E\\p{L}\\p{Nd}]+)',
        ];
        $numericConfig = [
            'minimum_username_length' => 4,
            'maximum_username_length' => 5,
            'username_pattern' => 'numeric',
        ];
        $alnumConfig = [
            'minimum_username_length' => 4,
            'maximum_username_length' => 5,
            'username_pattern' => 'alphanumeric',
        ];
        $patternConfig = [
            'minimum_username_length' => 4,
            'maximum_username_length' => 5,
            'username_pattern' => '([\p{L}\p{N}]+)',
        ];
        return [
            // Default pattern:
            [
                $defaultConfig,
                '"foo"',
                \VuFind\Exception\Auth::class,
                'username_error_invalid',
            ],
            [
                $defaultConfig,
                '😀',
                \VuFind\Exception\Auth::class,
                'username_error_invalid',
            ],
            [
                $defaultConfig,
                "!#$%&'*+-/=?^_`{|}~abcδä",
                \Exception::class,
                'Service manager missing', // == success
            ],

            // Numeric:
            [
                $numericConfig,
                '123',
                \VuFind\Exception\Auth::class,
                'username_minimum_length',
            ],
            [
                $numericConfig,
                '123456',
                \VuFind\Exception\Auth::class,
                'username_maximum_length',
            ],
            [
                $numericConfig,
                'abcd',
                \VuFind\Exception\Auth::class,
                'username_error_invalid',
            ],
            [
                $numericConfig,
                '1234',
                \Exception::class,
                'Service manager missing', // == success
            ],
            [
                $numericConfig,
                '12345',
                \Exception::class,
                'Service manager missing', // == success
            ],

            // Alphanumeric:
            [
                $alnumConfig,
                '1ab',
                \VuFind\Exception\Auth::class,
                'username_minimum_length',
            ],
            [
                $alnumConfig,
                '1abcde',
                \VuFind\Exception\Auth::class,
                'username_maximum_length',
            ],
            [
                $alnumConfig,
                'pass!',
                \VuFind\Exception\Auth::class,
                'username_error_invalid',
            ],
            [
                $alnumConfig,
                '1abc',
                \Exception::class,
                'Service manager missing', // == success
            ],
            [
                $alnumConfig,
                '1abcd',
                \Exception::class,
                'Service manager missing', // == success
            ],

            // Pattern:
            [
                $patternConfig,
                '1abc!',
                \VuFind\Exception\Auth::class,
                'username_error_invalid',
            ],
            [
                $patternConfig,
                'abd/e',
                \VuFind\Exception\Auth::class,
                'username_error_invalid',
            ],
            [
                $patternConfig,
                '1abcÖ',
                \Exception::class,
                'Service manager missing', // == success
            ],
            [
                $patternConfig,
                'abcδ',
                \Exception::class,
                'Service manager missing', // == success
            ],
        ];
    }

    /**
     * Test validation of create request with a username policy.
     *
     * @param array  $authConfig             Authentication configuration
     * @param string $username               Username for test
     * @param string $expectedExceptionClass Expected exception class
     * @param string $expectedExceptionMsg   Expected exception message
     *
     * @dataProvider getTestCreateWithUsernamePolicyData
     *
     * @return void
     */
    public function testCreateWithUsernamePolicy(
        array $authConfig,
        string $username,
        string $expectedExceptionClass,
        string $expectedExceptionMsg
    ): void {
        $config = new Config(
            [
                'Authentication' => $authConfig,
            ]
        );
        $db = new Database();
        $db->setConfig($config);
        $arr = $this->getCreateParams();
        $arr['username'] = $username;
        $this->expectException($expectedExceptionClass);
        $this->expectExceptionMessage($expectedExceptionMsg);
        $db->create($this->getRequest($arr));
    }

    /**
     * Test missing table manager.
     *
     * @return void
     */
    public function testCreateWithMissingTableManager(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Service manager missing');

        $db = new Database();
        $db->create($this->getRequest($this->getCreateParams()));
    }

    /**
     * Test creation w/duplicate email.
     *
     * @return void
     */
    public function testCreateDuplicateEmail(): void
    {
        $this->expectException(\VuFind\Exception\Auth::class);
        $this->expectExceptionMessage('That email address is already used');

        // Fake services:
        $service = $this->createMock(UserServiceInterface::class);
        $mockUser = $this->createMock(UserEntityInterface::class);
        $service->expects($this->once())->method('getUserByUsername')->with('good')->willReturn(null);
        $service->expects($this->once())->method('getUserByEmail')->with('me@mysite.com')->willReturn($mockUser);
        $db = $this->getDatabase($service);
        $this->assertEquals(
            false,
            $db->create($this->getRequest($this->getCreateParams()))
        );
    }

    /**
     * Test creation w/duplicate username.
     *
     * @return void
     */
    public function testCreateDuplicateUsername(): void
    {
        $this->expectException(\VuFind\Exception\Auth::class);
        $this->expectExceptionMessage('That username is already taken');

        // Fake services:
        $service = $this->createMock(UserServiceInterface::class);
        $mockUser = $this->createMock(UserEntityInterface::class);
        $service->expects($this->once())->method('getUserByUsername')->with('good')->willReturn($mockUser);
        $db = $this->getDatabase($service);
        $this->assertEquals(
            false,
            $db->create($this->getRequest($this->getCreateParams()))
        );
    }

    /**
     * Test successful creation.
     *
     * @return void
     */
    public function testSuccessfulCreation(): void
    {
        // Fake services:
        $service = $this->createMock(UserServiceInterface::class);
        $mockUser = $this->createMock(UserEntityInterface::class);
        $service->expects($this->once())->method('createEntityForUsername')->with('good')->willReturn($mockUser);
        $service->expects($this->once())->method('persistEntity')->with($mockUser);
        $service->expects($this->once())->method('getUserByUsername')->with('good')->willReturn(null);
        $service->expects($this->once())->method('getUserByEmail')->with('me@mysite.com')->willReturn(null);
        $db = $this->getDatabase($service);
        $user = $db->create($this->getRequest($this->getCreateParams()));
        $this->assertIsObject($user);
    }

    // INTERNAL API

    /**
     * Get fake create account parameters.
     *
     * @return array
     */
    protected function getCreateParams(): array
    {
        return [
            'firstname' => 'Foo',
            'lastname' => 'Bar',
            'username' => 'good',
            'password' => 'pass',
            'password2' => 'pass',
            'email' => 'me@mysite.com',
        ];
    }

    /**
     * Get a fake HTTP request.
     *
     * @param array $post POST parameters
     *
     * @return MockObject&Request
     */
    protected function getRequest($post = []): MockObject&Request
    {
        $post = new Parameters($post);
        $request = $this->getMockBuilder(Request::class)
            ->onlyMethods(['getPost'])->getMock();
        $request->expects($this->any())->method('getPost')->willReturn($post);
        return $request;
    }

    /**
     * Get a handler w/ fake table manager.
     *
     * @param UserServiceInterface $service Mock user database service
     *
     * @return Database
     */
    protected function getDatabase(UserServiceInterface $service): Database
    {
        $serviceManager = $this->getMockBuilder(\VuFind\Db\Service\PluginManager::class)
            ->disableOriginalConstructor()->onlyMethods(['get'])->getMock();
        $serviceManager->expects($this->any())->method('get')
            ->with($this->equalTo(UserServiceInterface::class))
            ->willReturn($service);

        $db = new Database();
        $db->setDbServiceManager($serviceManager);
        return $db;
    }
}
