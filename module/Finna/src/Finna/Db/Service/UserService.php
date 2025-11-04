<?php

/**
 * Database service for user.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024-2025.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace Finna\Db\Service;

use DateTime;
use Doctrine\ORM\EntityManager;
use Finna\Db\Entity\UserCardEntityInterface;
use Finna\Db\Entity\UserEntityInterface;
use Finna\Db\Entity\UserListEntityInterface;
use Laminas\Session\Container as SessionContainer;
use VuFind\Crypt\HMAC;
use VuFind\Db\Entity\PluginManager as EntityPluginManager;
use VuFind\Db\Feature\DateTimeTrait;
use VuFind\Db\PersistenceManager;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;
use VuFind\Db\Service\UserCardServiceInterface;

use function assert;
use function in_array;

/**
 * Database service for user.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class UserService extends \VuFind\Db\Service\UserService implements
    DbServiceAwareInterface,
    UserServiceInterface
{
    use DateTimeTrait;
    use DbServiceAwareTrait;

    /**
     * Constructor
     *
     * @param SessionContainer $userSessionContainer Session container for user data
     * @param array            $config               Main configuration
     * @param HMAC             $hmac                 HMAC service
     */
    public function __construct(
        EntityManager $entityManager,
        EntityPluginManager $entityPluginManager,
        PersistenceManager $persistenceManager,
        SessionContainer $userSessionContainer,
        protected array $config,
        protected HMAC $hmac
    ) {
        parent::__construct($entityManager, $entityPluginManager, $persistenceManager, $userSessionContainer);
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
        return parent::createEntityForUsername($this->addInstitutionPrefix($username));
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
        if ('email' === $fieldName) {
            $dql = 'SELECT u FROM ' . UserEntityInterface::class . ' u'
                . " WHERE u.authMethod = 'database' AND u.email = :email AND u.username LIKE :usernamePrefix";
            $query = $this->entityManager->createQuery($dql);
            $query->setParameters([
                'email' => $fieldValue,
                'usernamePrefix' => $this->addInstitutionPrefix('') . '%',
            ]);
            return $query->getOneOrNullResult();
        }
        if (in_array($fieldName, ['username', 'cat_id'])) {
            $fieldValue = $this->addInstitutionPrefix($fieldValue);
        }
        return parent::getUserByField($fieldName, $fieldValue);
    }

    /**
     * Update due date reminder setting for a user
     *
     * @param UserEntityInterface $user            User
     * @param int                 $dueDateReminder Due date reminder (days in advance)
     *
     * @return void
     */
    public function setDueDateReminderForUser(UserEntityInterface $user, int $dueDateReminder): void
    {
        assert($user instanceof UserEntityInterface);
        $user->setFinnaDueDateReminder($dueDateReminder);
        $this->persistEntity($user);
        $userCardService = $this->getDbService(UserCardServiceInterface::class);
        foreach ($userCardService->getLibraryCards($user, null, $user->getCatUsername()) as $card) {
            assert($card instanceof UserCardEntityInterface);
            $card->setFinnaDueDateReminder($dueDateReminder);
            $userCardService->persistEntity($card);
        }
    }

    /**
     * Retrieve protected users.
     *
     * @return UserEntityInterface[]
     */
    public function getProtectedUsers(): array
    {
        return $this->entityManager->getRepository(UserEntityInterface::class)->findBy(['finnaProtected' => true]);
    }

    /**
     * Get users that haven't logged in since the given date.
     *
     * @param DateTime $lastLoginDateThreshold Last login date threshold
     *
     * @return UserEntityInterface[]
     */
    public function getExpiringUsers(DateTime $lastLoginDateThreshold): array
    {
        $dql = 'SELECT ul.user FROM ' . UserListEntityInterface::class . ' ul'
            . ' WHERE ul.finnaProtected = 1';
        $subQuery = $this->entityManager->createQuery($dql);

        $dql = 'SELECT u FROM ' . UserEntityInterface::class . ' u'
            . ' WHERE u.lastLogin != :nullDate AND u.lastLogin < :lastLoginDateThreshold AND u NOT IN (:subQuery)';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters([
            'nullDate' => $this->getNonNullableDateTimeFromNullable(null),
            'lastLoginDateThreshold' => $lastLoginDateThreshold,
            'subQuery' => $subQuery,
        ]);
        return $query->getResult();
    }

    /**
     * Get users with due date reminders.
     *
     * @return UserEntityInterface[]
     */
    public function getUsersWithDueDateReminders(): array
    {
        $dql = 'SELECT uc FROM ' . UserCardEntityInterface::class . ' WHERE uc.finnaDueDateReminder > 0';
        $subQuery = $this->entityManager->createQuery($dql);

        $dql = 'SELECT u FROM ' . UserEntityInterface::class . ' u'
            . ' WHERE u IN (:subQuery)';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters(compact('subQuery'));
        return $query->getResult();
    }

    /**
     * Check if given nickname is available
     *
     * @param string $nickname Nickname
     *
     * @return bool
     */
    public function isNicknameAvailable(string $nickname): bool
    {
        return null
            === $this->entityManager->getRepository(UserEntityInterface::class)->findBy(['finnaNickname' => $nickname]);
    }

    /**
     * Add institution prefix to a string if it isn't already prefixed
     *
     * @param string $value String
     *
     * @return string
     */
    protected function addInstitutionPrefix(string $value): string
    {
        if ($prefix = $this->config['Site']['institution'] ?? null) {
            $prefix .= ':';
            if (!str_starts_with($value, $prefix)) {
                $value = $prefix . $value;
            }
        }
        return $value;
    }
}
