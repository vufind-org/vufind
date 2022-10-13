<?php
/**
 * Database authentication test class.
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

use Laminas\Config\Config;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Stdlib\Parameters;
use VuFind\Auth\Database;

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
    public function testEmptyCreateRequest()
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
    public function testEmptyPasswordCreateRequest()
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
    public function testMismatchedPasswordCreateRequest()
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
    public function getTestCreateWithPasswordPolicyData(): array
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
                'DB table manager missing.', // == success
            ],
            [
                $numericConfig,
                '12345',
                \Exception::class,
                'DB table manager missing.', // == success
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
                'DB table manager missing.', // == success
            ],
            [
                $alnumConfig,
                '1abcd',
                \Exception::class,
                'DB table manager missing.', // == success
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
                'DB table manager missing.', // == success
            ],
            [
                $patternConfig,
                'abcδ',
                \Exception::class,
                'DB table manager missing.', // == success
            ],
        ];
    }

    /**
     * Test validation of create request with a password policy.
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
                'Authentication' => $authConfig
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
                    'password_pattern' => 'a/'
                ]
            ]
        );
        $db = new Database();
        $db->setConfig($config);
        $arr = $this->getCreateParams();
        $arr['password'] = 'abcδ';
        $arr['password2'] = 'abcδ';
        $this->expectWarning();
        $db->create($this->getRequest($arr));
    }

    /**
     * Test missing table manager.
     *
     * @return void
     */
    public function testCreateWithMissingTableManager()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('DB table manager missing.');

        $db = new Database();
        $db->create($this->getRequest($this->getCreateParams()));
    }

    /**
     * Test creation w/duplicate email.
     *
     * @return void
     */
    public function testCreateDuplicateEmail()
    {
        $this->expectException(\VuFind\Exception\Auth::class);
        $this->expectExceptionMessage('That email address is already used');

        // Fake services:
        $table = $this->getMockTable(['getByEmail', 'getByUsername']);
        $table->expects($this->once())->method('getByEmail')
            ->with($this->equalTo('me@mysite.com'))
            ->will($this->returnValue(true));
        $table->expects($this->any())->method('getByUsername')
            ->with($this->equalTo('good'))
            ->will($this->returnValue(false));
        $db = $this->getDatabase($table);
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
    public function testCreateDuplicateUsername()
    {
        $this->expectException(\VuFind\Exception\Auth::class);
        $this->expectExceptionMessage('That username is already taken');

        // Fake services:
        $table = $this->getMockTable(['getByUsername']);
        $table->expects($this->any())->method('getByUsername')
            ->with($this->equalTo('good'))
            ->will($this->returnValue(true));
        $db = $this->getDatabase($table);
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
    public function testSuccessfulCreation()
    {
        // Fake services:
        $table = $this->getMockTable(['insert', 'getByEmail', 'getByUsername']);
        $table->expects($this->once())->method('getByEmail')
            ->with($this->equalTo('me@mysite.com'))
            ->will($this->returnValue(false));
        $table->expects($this->any())->method('getByUsername')
            ->with($this->equalTo('good'))
            ->will($this->returnValue(false));
        $db = $this->getDatabase($table);
        $prototype = $table->getResultSetPrototype()->getArrayObjectPrototype();
        $prototype->expects($this->once())->method('save');
        $user = $db->create($this->getRequest($this->getCreateParams()));
        $this->assertTrue(is_object($user));
    }

    // INTERNAL API

    /**
     * Get fake create account parameters.
     *
     * @return array
     */
    protected function getCreateParams()
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
     * Get a mock row object
     *
     * @return \VuFind\Db\Row\User
     */
    protected function getMockRow()
    {
        return $this->getMockBuilder(\VuFind\Db\Row\User::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Get a mock table object
     *
     * @param array $methods Methods to mock
     *
     * @return \VuFind\Db\Table\User
     */
    protected function getMockTable($methods = [])
    {
        $methods[] = 'getResultSetPrototype';
        $mock = $this->getMockBuilder(\VuFind\Db\Table\User::class)
            ->disableOriginalConstructor()
            ->onlyMethods($methods)
            ->getMock();
        $mock->expects($this->any())->method('getResultSetPrototype')
            ->will(
                $this->returnValue(
                    new ResultSet(
                        ResultSet::TYPE_ARRAYOBJECT,
                        $this->getMockRow()
                    )
                )
            );
        return $mock;
    }

    /**
     * Get a fake HTTP request.
     *
     * @param array $post POST parameters
     *
     * @return \Laminas\Http\PhpEnvironment\Request
     */
    protected function getRequest($post = [])
    {
        $post = new Parameters($post);
        $request = $this->getMockBuilder(\Laminas\Http\PhpEnvironment\Request::class)
            ->onlyMethods(['getPost'])->getMock();
        $request->expects($this->any())->method('getPost')
            ->will($this->returnValue($post));
        return $request;
    }

    /**
     * Get a handler w/ fake table manager.
     *
     * @param object $table Mock table.
     *
     * @return Database
     */
    protected function getDatabase($table)
    {
        $tableManager = $this->getMockBuilder(\VuFind\Db\Table\PluginManager::class)
            ->disableOriginalConstructor()->onlyMethods(['get'])->getMock();
        $tableManager->expects($this->once())->method('get')
            ->with($this->equalTo('User'))
            ->will($this->returnValue($table));

        $db = new Database();
        $db->setDbTableManager($tableManager);
        return $db;
    }
}
