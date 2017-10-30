<?php
/**
 * Trait for user row functionality
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015-2016.
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
 * @package  Db_Row
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace Finna\Db\Row;

/**
 * Fake database row to represent a user in privacy mode.
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
trait FinnaUserTrait
{
    /**
     * Activate a library card for the given username
     *
     * @param int $id Library card ID
     *
     * @return void
     * @throws \VuFind\Exception\LibraryCard
     */
    public function activateLibraryCard($id)
    {
        if (!$this->libraryCardsEnabled()) {
            throw new \VuFind\Exception\LibraryCard('Library Cards Disabled');
        }
        $userCard = $this->getDbTable('UserCard');
        $row = $userCard->select(['id' => $id, 'user_id' => $this->id])->current();

        if (!empty($row)) {
            $this->cat_username = $row->cat_username;
            $this->cat_password = $row->cat_password;
            $this->cat_pass_enc = $row->cat_pass_enc;
            $this->home_library = $row->home_library;
            $this->finna_due_date_reminder = $row->finna_due_date_reminder;
            $this->save();
        }
    }

    /**
     * Get a display name
     *
     * @return string
     */
    public function getDisplayName()
    {
        if ($this->firstname && $this->lastname) {
            return $this->firstname . ' ' . $this->lastname;
        }
        if ($this->firstname || $this->lastname) {
            return $this->firstname . $this->lastname;
        }
        if ($this->email) {
            return $this->email;
        }
        list(, $username) = explode(':', $this->username);
        return $username;
    }

    /**
     * Change due date reminder setting.
     *
     * @param int $remind New due date reminder setting.
     *
     * @return mixed           The output of the save method.
     */
    public function setFinnaDueDateReminder($remind)
    {
        $this->finna_due_date_reminder = $remind;
        $this->updateLibraryCardEntry();
        return $this->save();
    }

    /**
     * Verify that the current card information exists in user's library cards
     * (if enabled) and is up to date.
     *
     * @return void
     * @throws \VuFind\Exception\PasswordSecurity
     */
    protected function updateLibraryCardEntry()
    {
        if (!$this->libraryCardsEnabled() || empty($this->cat_username)) {
            return;
        }

        $userCard = $this->getDbTable('UserCard');
        $row = $userCard->select(
            ['user_id' => $this->id, 'cat_username' => $this->cat_username]
        )->current();
        if (empty($row)) {
            $row = $userCard->createRow();
            $row->user_id = $this->id;
            $row->cat_username = $this->cat_username;
            $row->card_name = $this->cat_username;
            $row->created = date('Y-m-d H:i:s');
        }
        // Always update home library, password and due date reminder
        $row->home_library = $this->home_library;
        $row->cat_password = $this->cat_password;
        $row->cat_pass_enc = $this->cat_pass_enc;
        $row->finna_due_date_reminder = $this->finna_due_date_reminder;

        $row->save();
    }
}
