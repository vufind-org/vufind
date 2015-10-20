<?php

/**
 * Trait with utility methods for user creation/management. Assumes that it
 * will be applied to a subclass of DbTestCase.
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
namespace VuFindTest\Unit;

/**
 * Trait with utility methods for user creation/management. Assumes that it
 * will be applied to a subclass of DbTestCase.
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
trait UserCreationTrait
{
    /**
     * Static setup support function to fail if users already exist in the database.
     * We want to ensure a clean state for each test!
     *
     * @return mixed
     */
    protected static function failIfUsersExist()
    {
        // If CI is not running, all tests were skipped, so no work is necessary:
        $test = new static();   // create instance of current class
        if (!$test->continuousIntegrationRunning()) {
            return;
        }
        // Fail if there are already users in the database (we don't want to run this
        // on a real system -- it's only meant for the continuous integration server)
        $userTable = $test->getTable('User');
        if (count($userTable->select()) > 0) {
            return self::fail(
                'Test cannot run with pre-existing user data!'
            );
        }
    }

    /**
     * Static teardown support function to destroy user accounts. Accounts are
     * expected to exist, and the method will fail if they are missing.
     *
     * @param array|string $users User(s) to delete
     *
     * @return void
     *
     * @throws \Exception
     */
    protected static function removeUsers($users)
    {
        // If CI is not running, all tests were skipped, so no work is necessary:
        $test = new static();   // create instance of current class
        if (!$test->continuousIntegrationRunning()) {
            return;
        }

        // Delete test user
        $userTable = $test->getTable('User');
        foreach ((array)$users as $username) {
            $user = $userTable->getByUsername($username, false);
            if (empty($user)) {
                throw new \Exception('Problem deleting expected user.');
            }
            $user->delete();
        }
    }
}