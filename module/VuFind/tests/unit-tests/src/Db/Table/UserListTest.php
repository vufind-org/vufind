<?php
/**
 * UserList Test Class
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\Db\Table;
use VuFind\Db\Table\UserList;

/**
 * UserList Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class UserListTest extends \VuFindTest\Unit\DbTestCase
{
    /**
     * Test that an exception is thrown if a non-logged-in user tries to create a new
     * list.
     *
     * @return void
     */
    public function testLoginRequiredToCreateList()
    {
        $this->setExpectedException('VuFind\Exception\LoginRequired');
        $table = $this->getTable('UserList');
        $list = $table->getNew(false);
    }

    /**
     * Test that a new list contains the appropriate user ID.
     *
     * @return void
     */
    public function testNewListContainsCreatorUserId()
    {
        $table = $this->getTable('UserList');
        $user = new \VuFind\Db\Row\User($table->getAdapter());
        $user->id = '1234';
        $list = $table->getNew($user);
        $this->assertEquals('1234', $list->user_id);
    }

    /**
     * Test that new lists are distinct (not references to same object).
     *
     * @return void
     */
    public function testNewListsAreDistinct()
    {
        $table = $this->getTable('UserList');
        $user = new \VuFind\Db\Row\User($table->getAdapter());
        $user->id = '1234';
        $list1 = $table->getNew($user);
        $list2 = $table->getNew($user);
        $list1->title = 'list 1';
        $list2->title = 'list 2';
        $this->assertEquals('list 1', $list1->title);
        $this->assertEquals('list 2', $list2->title);
    }
}