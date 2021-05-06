<?php
/**
 * UserList Test Class
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
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
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\Db\Table;

use VuFind\Db\Row\User;
use VuFind\Db\Table\UserList;

/**
 * UserList Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class UserListTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get a mock table object.
     *
     * @return UserList
     */
    protected function getMockTable()
    {
        $table = $this->getMockBuilder(UserList::class)
            ->onlyMethods(['createRow'])
            ->disableOriginalConstructor()->getMock();
        $rowCallback = function (): \VuFind\Db\Row\UserList {
            return $this->getMockBuilder(\VuFind\Db\Row\UserList::class)
                ->onlyMethods([])
                ->disableOriginalConstructor()->getMock();
        };
        $table->expects($this->any())->method('createRow')
            ->will($this->returnCallback($rowCallback));
        return $table;
    }

    /**
     * Create a mock user object.
     *
     * @return User
     */
    protected function getMockUser()
    {
        $user = $this->getMockBuilder(User::class)
            ->onlyMethods([])
            ->disableOriginalConstructor()->getMock();
        $user->id = '1234';
        return $user;
    }

    /**
     * Test that an exception is thrown if a non-logged-in user tries to create a new
     * list.
     *
     * @return void
     */
    public function testLoginRequiredToCreateList()
    {
        $this->expectException(\VuFind\Exception\LoginRequired::class);

        $table = $this->getMockTable();
        $list = $table->getNew(false);
    }

    /**
     * Test that a new list contains the appropriate user ID.
     *
     * @return void
     */
    public function testNewListContainsCreatorUserId()
    {
        $table = $this->getMockTable();
        $list = $table->getNew($this->getMockUser());
        $this->assertEquals('1234', $list->user_id);
    }

    /**
     * Test that new lists are distinct (not references to same object).
     *
     * @return void
     */
    public function testNewListsAreDistinct()
    {
        $table = $this->getMockTable();
        $user = $this->getMockUser();
        $list1 = $table->getNew($user);
        $list2 = $table->getNew($user);
        $list1->title = 'list 1';
        $list2->title = 'list 2';
        $this->assertEquals('list 1', $list1->title);
        $this->assertEquals('list 2', $list2->title);
    }
}
