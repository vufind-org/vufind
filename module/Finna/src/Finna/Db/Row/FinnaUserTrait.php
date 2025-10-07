<?php

/**
 * Trait for user row functionality
 *
 * PHP version 8
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

use DateTime;
use Laminas\Db\ResultSet\ResultSetInterface;
use Laminas\Db\Sql\Expression;

use function count;

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
     * ILS Connection
     *
     * @var \VuFind\ILS\Connection
     */
    protected $ils = null;

    /**
     * Set ILS Connection
     *
     * @param \VuFind\ILS\Connection $ils ILS Connection
     *
     * @return void
     */
    public function setILS(\VuFind\ILS\Connection $ils)
    {
        $this->ils = $ils;
    }

    /**
     * Get number of distinct user resources in all lists.
     *
     * @return int
     */
    public function getNumOfResources()
    {
        $resource = $this->getDbTable('Resource');
        $userResources = $resource->getFavorites(
            $this->id,
            null,
            null,
            null
        );
        return count($userResources);
    }

    /**
     * Save ILS ID.
     *
     * @param string $catId Catalog ID to save.
     *
     * @return mixed        The output of the save method.
     * @throws \VuFind\Exception\PasswordSecurity
     */
    public function saveCatalogId($catId)
    {
        if (isset($this->config->Site->institution)) {
            $catId = $this->config->Site->institution . ":$catId";
        }
        return parent::saveCatalogId($catId);
    }

    /**
     * Catalog id setter
     *
     * @param ?string $catId Catalog id
     *
     * @return static
     */
    public function setCatId(?string $catId): static
    {
        if ($catId && null !== ($institution = $this->config->Site->institution ?? null)) {
            $catId = "$institution:$catId";
        }
        return parent::setCatId($catId);
    }

    /**
     * Add/update a resource in the user's account.
     *
     * @param array                   $resources       The resources to add/update
     * @param \VuFind\Db\Row\UserList $list            The list to store the resource
     * in.
     * @param array                   $tagArray        An array of tags to associate
     * with the resource.
     * @param string                  $notes           User notes about the resource.
     * @param bool                    $replaceExisting Whether to replace all
     * existing tags (true) or append to the existing list (false).
     * @param int                     $index           First available custom order
     *                                                 index if used.
     *
     * @return void
     */
    public function saveResources(
        array $resources,
        \VuFind\Db\Row\UserList $list,
        array $tagArray,
        string $notes,
        bool $replaceExisting = true,
        int $index = 0
    ) {
        // Create the resource link if it doesn't exist and update the notes in any
        // case:
        $linkTable = $this->getDbTable('UserResource');
        foreach ($resources as $resource) {
            $linkTable->createOrUpdateLink(
                $resource->id,
                $this->id,
                $list->id,
                $notes,
                ($index ? $index++ : null)
            );
            // If we're replacing existing tags, delete the old ones before adding
            // the new ones:
            if ($replaceExisting) {
                $resource->deleteTags($this, $list->id);
            }
            // Add the new tags:
            foreach ($tagArray as $tag) {
                $resource->addTag($tag, $this, $list->id);
            }
        }
    }

    /**
     * Get all library cards associated with the user.
     *
     * @return \Laminas\Db\ResultSet\AbstractResultSet
     * @throws \VuFind\Exception\LibraryCard
     */
    public function getLibraryCards()
    {
        if (!$this->libraryCardsEnabled()) {
            return new \Laminas\Db\ResultSet\ResultSet();
        }

        $userId = $this->id;
        $loginTargets = null;
        if ($this->ils && $this->ils->checkCapability('getLoginDrivers')) {
            $loginTargets = $this->ils->getLoginDrivers();
            $loginTargets = array_map(
                function ($a) {
                    return "$a.";
                },
                $loginTargets
            );
        }
        $callback = function ($select) use ($userId, $loginTargets) {
            $select->where->equalTo('user_id', $userId);
            if (!empty($loginTargets)) {
                $select->where->in(
                    new Expression(
                        "substring(cat_username, 1, locate('.', cat_username))",
                        null,
                        [Expression::TYPE_LITERAL]
                    ),
                    $loginTargets
                );
            }
        };

        $userCard = $this->getDbTable('UserCard');
        return $userCard->select($callback);
    }

    /**
     * Get library card data by user name
     *
     * @param string $catUserName User name
     *
     * @return ResultSetInterface
     * @throws \VuFind\Exception\LibraryCard
     */
    public function getLibraryCardsByUserName(
        string $catUserName
    ): ResultSetInterface {
        if (!$this->libraryCardsEnabled()) {
            throw new \VuFind\Exception\LibraryCard('Library Cards Disabled');
        }

        $userCard = $this->getDbTable('UserCard');
        return $userCard->select(
            [
                'user_id' => $this->id,
                'cat_username' => $catUserName,
            ]
        );
    }

    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get login token.
     *
     * @param string $userId User ID
     *
     * @return array
     */
    public function getLoginTokens(string $userId): array
    {
        $tokenTable = $this->getDbTable('LoginToken');
        return $tokenTable->getByUserId($userId);
    }

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
        return $this->getUsername();
    }

    /**
     * Get a displayable version of username
     *
     * @return string
     */
    public function getDisplayableUsername(): string
    {
        if (strpos($this->username, ':')) {
            [$view, $username] = explode(':', $this->username, 2);
        } else {
            $username = $this->username;
        }
        if ($this->auth_method === 'multiils') {
            $parts = explode('.', $username, 2);
            $displayedName = $parts[1] ?? $parts[0];
        } else {
            $displayedName = $username;
        }
        if (isset($view)) {
            $displayedName .= ' (' . $view . ')';
        }
        return $displayedName;
    }

    /**
     * Due date reminder setting setter
     *
     * @param int $remind New due date reminder setting.
     *
     * @return static
     */
    public function setFinnaDueDateReminder(int $remind): static
    {
        $this->finna_due_date_reminder = $remind;
        return $this;
    }

    /**
     * Due date reminder setting getter
     *
     * @return int
     */
    public function getFinnaDueDateReminder(): int
    {
        return $this->finna_due_date_reminder;
    }

    /**
     * Nickname setter
     *
     * @param ?string $nickname Nickname or null for none
     *
     * @return static
     */
    public function setFinnaNickname(?string $nickname): static
    {
        $this->finna_nickname = $nickname;
        return $this;
    }

    /**
     * Nickname getter
     *
     * @return ?string
     */
    public function getFinnaNickName(): ?string
    {
        return $this->finna_nickname;
    }

    /**
     * Protection status setter
     *
     * @param bool $protected Is the user protected
     *
     * @return static
     */
    public function setFinnaProtected(bool $protected): static
    {
        $this->finna_protected = $protected ? 1 : 0;
        return $this;
    }

    /**
     * Protection status getter
     *
     * @return bool
     */
    public function getFinnaProtected(): bool
    {
        return $this->finna_protected ? true : false;
    }

    /**
     * Last expiration reminder date setter
     *
     * @param ?DateTime $dateTime Expiration reminder date
     *
     * @return static
     */
    public function setFinnaLastExpirationReminderDate(?DateTime $dateTime): static
    {
        $this->finna_last_expiration_reminder = $dateTime ? $dateTime->format('Y-m-d H:i:s') : '2000-01-01 00:00:00';
        return $this;
    }

    /**
     * Last expiration reminder date getter
     *
     * @return DateTime
     */
    public function getFinnaLastExpirationReminderDate(): ?Datetime
    {
        return $this->finna_last_expiration_reminder !== '2000-01-01 00:00:00'
            ? DateTime::createFromFormat('Y-m-d H:i:s', $this->finna_last_expiration_reminder) : null;
    }
}
