<?php

/**
 * Database service for user.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @package  Database
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use Laminas\Log\LoggerAwareInterface;
use Laminas\Session\Container as SessionContainer;
use VuFind\Auth\UserSessionPersistenceInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Row\User as UserRow;
use VuFind\Db\Table\DbTableAwareInterface;
use VuFind\Db\Table\DbTableAwareTrait;
use VuFind\Log\LoggerAwareTrait;

/**
 * Database service for user.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class UserService extends AbstractDbService implements
    DbTableAwareInterface,
    LoggerAwareInterface,
    UserServiceInterface,
    UserSessionPersistenceInterface
{
    use DbTableAwareTrait;
    use LoggerAwareTrait;

    /**
     * Constructor
     *
     * @param SessionContainer $userSessionContainer Session container for user data
     */
    public function __construct(protected SessionContainer $userSessionContainer)
    {
    }

    /**
     * Create an entity for the specified username.
     *
     * @param string $username Username
     *
     * @return UserEntityInterface
     */
    public function createEntityForUsername(string $username): UserEntityInterface
    {
        return $this->getDbTable('User')->createRowForUsername($username);
    }

    /**
     * Delete a user entity.
     *
     * @param UserEntityInterface|int $userOrId User entity object or ID to delete
     *
     * @return void
     */
    public function deleteUser(UserEntityInterface|int $userOrId): void
    {
        $userId = $userOrId instanceof UserEntityInterface ? $userOrId->getId() : $userOrId;
        $this->getDbTable('User')->delete(['id' => $userId]);
    }

    /**
     * Retrieve a user object from the database based on ID.
     *
     * @param int $id ID.
     *
     * @return ?UserEntityInterface
     */
    public function getUserById(int $id): ?UserEntityInterface
    {
        return $this->getDbTable('User')->getById($id);
    }

    /**
     * Retrieve a user object from the database based on the given field.
     * Field name must be id, username, email, verify_hash or cat_id.
     *
     * @param string          $fieldName  Field name
     * @param int|string|null $fieldValue Field value
     *
     * @return ?UserEntityInterface
     */
    public function getUserByField(string $fieldName, int|string|null $fieldValue): ?UserEntityInterface
    {
        switch ($fieldName) {
            case 'email':
                return $this->getDbTable('User')->getByEmail($fieldValue);
            case 'id':
                return $this->getDbTable('User')->getById($fieldValue);
            case 'username':
                return $this->getDbTable('User')->getByUsername($fieldValue, false);
            case 'verify_hash':
                return $this->getDbTable('User')->getByVerifyHash($fieldValue);
            case 'cat_id':
                return $this->getDbTable('User')->getByCatalogId($fieldValue);
        }
        throw new \InvalidArgumentException('Field name must be id, username, email or cat_id');
    }

    /**
     * Retrieve a user object by catalog ID. Returns null if no match is found.
     *
     * @param string $catId Catalog ID
     *
     * @return ?UserEntityInterface
     */
    public function getUserByCatId(string $catId): ?UserEntityInterface
    {
        return $this->getUserByField('cat_id', $catId);
    }

    /**
     * Retrieve a user object by email address. Returns null if no match is found.
     *
     * @param string $email Email address
     *
     * @return ?UserEntityInterface
     */
    public function getUserByEmail(string $email): ?UserEntityInterface
    {
        return $this->getUserByField('email', $email);
    }

    /**
     * Retrieve a user object by username. Returns null if no match is found.
     *
     * @param string $username Username
     *
     * @return ?UserEntityInterface
     */
    public function getUserByUsername(string $username): ?UserEntityInterface
    {
        return $this->getUserByField('username', $username);
    }

    /**
     * Retrieve a user object by verify hash. Returns null if no match is found.
     *
     * @param string $hash Verify hash
     *
     * @return ?UserEntityInterface
     */
    public function getUserByVerifyHash(string $hash): ?UserEntityInterface
    {
        return $this->getUserByField('verify_hash', $hash);
    }

    /**
     * Update the user's email address, if appropriate. Note that this does NOT
     * automatically save the row; it assumes a subsequent call will be made to
     * persist the data.
     *
     * @param UserEntityInterface $user         User entity to update
     * @param string              $email        New email address
     * @param bool                $userProvided Was this email provided by the user (true) or
     * an automated lookup (false)?
     *
     * @return void
     */
    public function updateUserEmail(
        UserEntityInterface $user,
        string $email,
        bool $userProvided = false
    ): void {
        // Only change the email if it is a non-empty value and was user provided
        // (the user is always right) or the previous email was NOT user provided
        // (a value may have changed in an upstream system).
        if (!empty($email) && ($userProvided || !$user->hasUserProvidedEmail())) {
            $user->setEmail($email);
            $user->setHasUserProvidedEmail($userProvided);
        }
    }

    /**
     * Update session container to store data representing a user (used by privacy mode).
     *
     * @param UserEntityInterface $user User to store in session.
     *
     * @return void
     * @throws Exception
     */
    public function addUserDataToSession(UserEntityInterface $user): void
    {
        if ($user instanceof UserRow) {
            $this->userSessionContainer->userDetails = $user->toArray();
        } else {
            throw new \Exception($user::class . ' not supported by addUserDataToSession()');
        }
    }

    /**
     * Update session container to store user ID (used outside of privacy mode).
     *
     * @param int $id User ID
     *
     * @return void
     */
    public function addUserIdToSession(int $id): void
    {
        $this->userSessionContainer->userId = $id;
    }

    /**
     * Clear the user data from the session.
     *
     * @return void
     */
    public function clearUserFromSession(): void
    {
        unset($this->userSessionContainer->userId);
        unset($this->userSessionContainer->userDetails);
    }

    /**
     * Build a user entity using data from a session container. Return null if user
     * data cannot be found.
     *
     * @return ?UserEntityInterface
     */
    public function getUserFromSession(): ?UserEntityInterface
    {
        // If a user ID was persisted, that takes precedence:
        if (isset($this->userSessionContainer->userId)) {
            return $this->getUserById($this->userSessionContainer->userId);
        }
        if (isset($this->userSessionContainer->userDetails)) {
            $user = $this->createEntity();
            $user->exchangeArray($this->userSessionContainer->userDetails);
            return $user;
        }
        return null;
    }

    /**
     * Is there user data currently stored in the session container?
     *
     * @return bool
     */
    public function hasUserSessionData(): bool
    {
        return isset($this->userSessionContainer->userId)
            || isset($this->userSessionContainer->userDetails);
    }

    /**
     * Get all rows with catalog usernames.
     *
     * @return UserEntityInterface[]
     */
    public function getAllUsersWithCatUsernames(): array
    {
        $callback = function ($select) {
            $select->where->isNotNull('cat_username');
        };
        return iterator_to_array($this->getDbTable('User')->select($callback));
    }

    /**
     * Get user rows with insecure catalog passwords.
     *
     * @return UserEntityInterface[]
     */
    public function getInsecureRows(): array
    {
        return iterator_to_array($this->getDbTable('User')->getInsecureRows());
    }

    /**
     * Create a new user entity.
     *
     * @return UserEntityInterface
     */
    public function createEntity(): UserEntityInterface
    {
        return $this->getDbTable('User')->createRow();
    }
}
