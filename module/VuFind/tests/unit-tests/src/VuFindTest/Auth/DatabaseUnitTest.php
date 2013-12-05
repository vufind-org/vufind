<?php
/**
 * Database authentication test class.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFindTest\Auth;
use VuFind\Auth\Database, Zend\Stdlib\Parameters;

/**
 * Database authentication test class.
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class DatabaseUnitTest extends \VuFindTest\Unit\DbTestCase
{
    /**
     * Test validation of empty create request.
     *
     * @return void
     * @expectedException VuFind\Exception\Auth
     * @expectedExceptionMessage Username cannot be blank
     */
    public function testEmptyCreateRequest()
    {
        $db = new Database();
        $db->create($this->getRequest());
    }

    /**
     * Test validation of create request w/blank password.
     *
     * @return void
     * @expectedException VuFind\Exception\Auth
     * @expectedExceptionMessage Password cannot be blank
     */
    public function testEmptyPasswordCreateRequest()
    {
        $db = new Database();
        $arr = $this->getCreateParams();
        $arr['password'] = $arr['password2'] = '';
        $db->create($this->getRequest($arr));
    }

    /**
     * Test validation of create request w/mismatched passwords.
     *
     * @return void
     * @expectedException VuFind\Exception\Auth
     * @expectedExceptionMessage Passwords do not match
     */
    public function testMismatchedPasswordCreateRequest()
    {
        $db = new Database();
        $arr = $this->getCreateParams();
        $arr['password2'] = 'bad';
        $db->create($this->getRequest($arr));
    }

    /**
     * Test missing table manager.
     *
     * @return void
     * @expectedException Exception
     * @expectedExceptionMessage DB table manager missing.
     */
    public function testCreateWithMissingTableManager()
    {
        $db = new Database();
        $db->create($this->getRequest($this->getCreateParams()));
    }

    /**
     * Test creation w/duplicate email.
     *
     * @return void
     * @expectedException VuFind\Exception\Auth
     * @expectedExceptionMessage That email address is already used
     */
    public function testCreateDuplicateEmail()
    {
        // Fake services:
        $table = $this->getMock(
            'VuFind\Db\Table\Tags', array('getByEmail', 'getByUsername')
        );
        $table->expects($this->once())->method('getByEmail')
            ->with($this->equalTo('me@mysite.com'))
            ->will($this->returnValue(true));
        $table->expects($this->any())->method('getByUsername')
            ->with($this->equalTo('good'))
            ->will($this->returnValue(false));
        $db = $this->getDatabase($table);
        $this->assertEquals(
            false, $db->create($this->getRequest($this->getCreateParams()))
        );
    }

    /**
     * Test creation w/duplicate username.
     *
     * @return void
     * @expectedException VuFind\Exception\Auth
     * @expectedExceptionMessage That username is already taken
     */
    public function testCreateDuplicateUsername()
    {
        // Fake services:
        $table = $this->getMock(
            'VuFind\Db\Table\Tags', array('getByUsername')
        );
        $table->expects($this->any())->method('getByUsername')
            ->with($this->equalTo('good'))
            ->will($this->returnValue(true));
        $db = $this->getDatabase($table);
        $this->assertEquals(
            false, $db->create($this->getRequest($this->getCreateParams()))
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
        $table = $this->getMock(
            'VuFind\Db\Table\Tags', array('insert', 'getByEmail', 'getByUsername')
        );
        $table->expects($this->once())->method('insert');
        $table->expects($this->once())->method('getByEmail')
            ->with($this->equalTo('me@mysite.com'))
            ->will($this->returnValue(false));
        $table->expects($this->any())->method('getByUsername')
            ->with($this->equalTo('good'))
            ->will($this->returnValue(false));
        $db = $this->getDatabase($table);
        $this->assertEquals(
            false, $db->create($this->getRequest($this->getCreateParams()))
        );
    }

    // INTERNAL API

    /**
     * Get fake create account parameters.
     *
     * @return array
     */
    protected function getCreateParams()
    {
        return array(
            'firstname' => 'Foo',
            'lastname' => 'Bar',
            'username' => 'good',
            'password' => 'pass',
            'password2' => 'pass',
            'email' => 'me@mysite.com',
        );
    }

    /**
     * Get a fake HTTP request.
     *
     * @param array $post POST parameters
     *
     * @return \Zend\Http\PhpEnvironment\Request
     */
    protected function getRequest($post = array())
    {
        $post = new Parameters($post);
        $request
            = $this->getMock('Zend\Http\PhpEnvironment\Request', array('getPost'));
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
        $tableManager
            = $this->getMock('VuFind\Db\Table\PluginManager', array('get'));
        $tableManager->expects($this->once())->method('get')
            ->with($this->equalTo('User'))
            ->will($this->returnValue($table));

        $db = new Database();
        $db->setDbTableManager($tableManager);
        return $db;
    }
}