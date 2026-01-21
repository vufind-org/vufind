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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Database
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use DateTime;
use Doctrine\ORM\EntityManager;
use Laminas\Session\Container as SessionContainer;
use VuFind\Auth\UserSessionPersistenceInterface;
use VuFind\Db\Entity\PluginManager as EntityPluginManager;
use VuFind\Db\Entity\User;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\PersistenceManager;

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
    UserServiceInterface,
    UserSessionPersistenceInterface
{
    /**
     * Constructor
     *
     * @param EntityManager       $entityManager        Doctrine ORM entity manager
     * @param EntityPluginManager $entityPluginManager  VuFind entity plugin manager
     * @param PersistenceManager  $persistenceManager   Entity persistence manager
     * @param SessionContainer    $userSessionContainer Session container for user data
     */
    public function __construct(
        EntityManager $entityManager,
        EntityPluginManager $entityPluginManager,
        PersistenceManager $persistenceManager,
        protected SessionContainer $userSessionContainer
    ) {
        parent::__construct($entityManager, $entityPluginManager, $persistenceManager);
    }

    /**
     * Create an access_token entity object.
     *
     * @return UserEntityInterface
     */
    public function createEntity(): UserEntityInterface
    {
        return $this->entityPluginManager->get(UserEntityInterface::class);
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
        $user = $this->createEntity()
            ->setUsername($username)
            ->setCreated(new DateTime())
            ->setHasUserProvidedEmail(false);
        return $user;
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
        if ($user = $this->getDoctrineReference(UserEntityInterface::class, $userOrId)) {
            $this->deleteEntity($user);
        }
    }

    /**
     * Retrieve a user object from the database based on ID.
     *
     * @param int $id ID value.
     *
     * @return ?UserEntityInterface
     */
    public function getUserById(int $id): ?UserEntityInterface
    {
        return $this->entityManager->find(UserEntityInterface::class, $id);
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
        // Null ID lookups cannot possibly retrieve a value:
        if ($fieldName === 'id' && $fieldValue === null) {
            return null;
        }
        // Map expected incoming values (actual database columns) to legal values (Doctrine properties)
        $legalFieldMap = [
            'id' => 'id',
            'username' => 'username',
            'email' => 'email',
            'cat_id' => 'catId',
            'verify_hash' => 'verifyHash',
        ];
        // For now, only username lookups are case-insensitive:
        $caseInsensitive = $fieldName === 'username';
        if (isset($legalFieldMap[$fieldName])) {
            $where = $caseInsensitive
                ? 'LOWER(U.' . $legalFieldMap[$fieldName] . ') = LOWER(:fieldValue)'
                : 'U.' . $legalFieldMap[$fieldName] . ' = :fieldValue';
            $dql = 'SELECT U FROM ' . UserEntityInterface::class . ' U '
                . 'WHERE ' . $where;
            $parameters = compact('fieldValue');
            $query = $this->entityManager->createQuery($dql);
            $query->setParameters($parameters);
            $query->setMaxResults(1); // Not every field is guaranteed unique, so be sure to get just one!
            return $query->getOneOrNullResult();
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
        $this->userSessionContainer->userDetails = $user->toArray();
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
        $dql = 'SELECT u '
                . 'FROM ' . UserEntityInterface::class . ' u '
                . 'WHERE u.catUsername IS NOT NULL';
        $query = $this->entityManager->createQuery($dql);
        $result = $query->getResult();
        return $result;
    }

    /**
     * Get user rows with insecure catalog passwords.
     *
     * @return UserEntityInterface[]
     */
    public function getInsecureRows(): array
    {
        $dql = 'SELECT u '
                . 'FROM ' . UserEntityInterface::class . ' u '
                . "WHERE u.password != '' "
                . 'AND u.catPassword IS NOT NULL';
        $query = $this->entityManager->createQuery($dql);
        $result = $query->getResult();
        return $result;
    }
}
