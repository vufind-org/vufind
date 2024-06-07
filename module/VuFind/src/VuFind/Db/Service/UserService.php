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

use Doctrine\ORM\EntityManager;
use Laminas\Log\LoggerAwareInterface;
use Laminas\Session\Container as SessionContainer;
use VuFind\Auth\UserSessionPersistenceInterface;
use VuFind\Db\Entity\PluginManager as EntityPluginManager;
use VuFind\Db\Entity\Resource;
use VuFind\Db\Entity\User;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserList;
use VuFind\Db\Row\User as UserRow;
use VuFind\Db\Table\DbTableAwareInterface;
use VuFind\Db\Table\DbTableAwareTrait;
use VuFind\Log\LoggerAwareTrait;

use function in_array;

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
    DbServiceAwareInterface,
    UserServiceInterface,
    UserSessionPersistenceInterface
{
    use DbTableAwareTrait;
    use LoggerAwareTrait;
    use DbServiceAwareTrait;

    /**
     * Constructor
     *
     * @param EntityManager       $entityManager        Doctrine ORM entity manager
     * @param EntityPluginManager $entityPluginManager  VuFind entity plugin manager
     * @param SessionContainer    $userSessionContainer Session container for user data
     */
    public function __construct(
        EntityManager $entityManager,
        EntityPluginManager $entityPluginManager,
        protected SessionContainer $userSessionContainer
    ) {
        parent::__construct($entityManager, $entityPluginManager);
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
        return $this->entityManager->find($this->getEntityClass(User::class), $id);
    }

    /**
     * Add/update a resource in the user's account.
     *
     * @param Resource|int $resource        The resource to add/update
     * @param User|int     $user            Logged in user
     * @param UserList|int $list            The list to store the resource in.
     * @param array        $tagArray        An array of tags to associate with the resource.
     * @param string       $notes           User notes about the resource.
     * @param bool         $replaceExisting Whether to replace all existing tags (true) or append to the
     * existing list (false).
     *
     * @return void
     */
    public function saveResource(
        $resource,
        $user,
        $list,
        $tagArray,
        $notes,
        $replaceExisting = true
    ) {
        // Create the resource link if it doesn't exist and update the notes in any case:
        $linkService = $this->getDbService(UserResourceService::class);
        $linkService->createOrUpdateLink($resource, $user, $list, $notes);

        // If we're replacing existing tags, delete the old ones before adding the new ones:
        if ($replaceExisting) {
            $unlinker = $this->getDbService(TagService::class);
            $resourceId = $resource instanceof Resource ? $resource->getId() : $resource;
            $unlinker->destroyResourceLinks($resourceId, $user, $list);
        }

        // Add the new tags:
        foreach ($tagArray as $tag) {
            $tagService = $this->getDbService(TagService::class);
            $tagService->addTag($resource, $tag, $user, $list);
        }
    }

    /**
     * Retrieve a user object from the database based on the given field.
     * Field name must be id, username or cat_id.
     *
     * @param string          $fieldName  Field name
     * @param int|string|null $fieldValue Field value
     *
     * @return ?UserEntityInterface
     */
    public function getUserByField(string $fieldName, int|string|null $fieldValue): ?UserEntityInterface
    {
        $legalFields = ['id', 'username', 'cat_id'];
        if (in_array($fieldName, $legalFields)) {
            $dql = 'SELECT U FROM ' . $this->getEntityClass(User::class) . ' U '
                . 'WHERE U.' . $fieldName . ' = :fieldValue';
            $parameters = compact('fieldValue');
            $query = $this->entityManager->createQuery($dql);
            $query->setParameters($parameters);
            $result = current($query->getResult());
            return $result ?: null;
        }
        throw new \InvalidArgumentException('Field name must be id, username or cat_id');
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
     * Create a new user entity.
     *
     * @return UserEntityInterface
     */
    public function createEntity(): UserEntityInterface
    {
        return $this->getDbTable('User')->createRow();
    }
}
