<?php

/**
 * Database service for UserCard.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use DateTime;
use Doctrine\ORM\EntityManager;
use Psr\Log\LoggerAwareInterface;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Config\AccountCapabilities;
use VuFind\Db\Entity\PluginManager as EntityPluginManager;
use VuFind\Db\Entity\UserCardEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\PersistenceManager;
use VuFind\Log\LoggerAwareTrait;

use function count;
use function is_int;

/**
 * Database service for UserCard.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class UserCardService extends AbstractDbService implements
    DbServiceAwareInterface,
    LoggerAwareInterface,
    UserCardServiceInterface
{
    use DbServiceAwareTrait;
    use LoggerAwareTrait;

    /**
     * Constructor
     *
     * @param EntityManager       $entityManager       Doctrine ORM entity manager
     * @param EntityPluginManager $entityPluginManager VuFind entity plugin manager
     * @param PersistenceManager  $persistenceManager  Entity persistence manager
     * @param ILSAuthenticator    $ilsAuthenticator    ILS authenticator
     * @param AccountCapabilities $capabilities        Account capabilities configuration
     */
    public function __construct(
        EntityManager $entityManager,
        EntityPluginManager $entityPluginManager,
        PersistenceManager $persistenceManager,
        protected ILSAuthenticator $ilsAuthenticator,
        protected AccountCapabilities $capabilities
    ) {
        parent::__construct($entityManager, $entityPluginManager, $persistenceManager);
    }

    /**
     * Get user_card rows with insecure catalog passwords.
     *
     * @return UserCardEntityInterface[]
     */
    public function getInsecureRows(): array
    {
        $dql = 'SELECT UC FROM ' . UserCardEntityInterface::class
            . ' UC WHERE UC.catPassword IS NOT NULL';
        $query = $this->entityManager->createQuery($dql);
        return $query->getResult();
    }

    /**
     * Get user_card rows with catalog usernames set.
     *
     * @return UserCardEntityInterface[]
     */
    public function getAllRowsWithUsernames(): array
    {
        $dql = 'SELECT UC FROM ' . UserCardEntityInterface::class
            . ' UC WHERE UC.catUsername IS NOT NULL';
        $query = $this->entityManager->createQuery($dql);
        return $query->getResult();
    }

    /**
     * Get all library cards associated with the user.
     *
     * @param UserEntityInterface|int $userOrId    User object or identifier
     * @param ?int                    $id          Optional card ID filter
     * @param ?string                 $catUsername Optional catalog username filter
     *
     * @return UserCardEntityInterface[]
     */
    public function getLibraryCards(
        UserEntityInterface|int $userOrId,
        ?int $id = null,
        ?string $catUsername = null
    ): array {
        if (!$this->capabilities->libraryCardsEnabled()) {
            return [];
        }
        $dql = 'SELECT UC FROM ' . UserCardEntityInterface::class . ' UC ';
        $dqlWhere = ['UC.user = :user'];
        $parameters['user'] = $this->getDoctrineReference(UserEntityInterface::class, $userOrId);
        if (null !== $id) {
            $dqlWhere[] = 'UC.id = :id';
            $parameters['id'] = $id;
        }
        if (null !== $catUsername) {
            $dqlWhere[] = 'UC.catUsername = :catUsername';
            $parameters['catUsername'] = $catUsername;
        }
        $dql .= ' WHERE ' . implode(' AND ', $dqlWhere)
            . ' ORDER BY UC.id ASC';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $records = $query->getResult();
        return $records;
    }

    /**
     * Get or create library card data.
     *
     * @param UserEntityInterface|int $userOrId User object or identifier
     * @param ?int                    $id       Card ID to fetch (or null to create a new card)
     *
     * @return UserCardEntityInterface Card data if found; throws exception otherwise
     * @throws \VuFind\Exception\LibraryCard
     */
    public function getOrCreateLibraryCard(UserEntityInterface|int $userOrId, ?int $id = null): UserCardEntityInterface
    {
        if (!$this->capabilities->libraryCardsEnabled()) {
            throw new \VuFind\Exception\LibraryCard('Library Cards Disabled');
        }
        if ($id === null) {
            $row = $this->createEntity()
                ->setCardName('')
                ->setUser($this->getDoctrineReference(UserEntityInterface::class, $userOrId))
                ->setCatUsername('')
                ->setRawCatPassword('');
        } else {
            $row = current($this->getLibraryCards($userOrId, $id));
            if ($row === false) {
                throw new \VuFind\Exception\LibraryCard('Library Card Not Found');
            }
        }
        return $row;
    }

    /**
     * Delete library card.
     *
     * @param UserEntityInterface         $user     User owning card to delete
     * @param UserCardEntityInterface|int $userCard UserCard id or object to be deleted
     *
     * @return bool
     * @throws \Exception
     */
    public function deleteLibraryCard(UserEntityInterface $user, UserCardEntityInterface|int $userCard): bool
    {
        if (!$this->capabilities->libraryCardsEnabled()) {
            throw new \VuFind\Exception\LibraryCard('Library Cards Disabled');
        }
        $cardId = is_int($userCard) ? $userCard : $userCard->getId();
        $row = current($this->getLibraryCards($user, $cardId));
        if (!$row) {
            throw new \Exception('Library card not found');
        }

        try {
            $this->deleteEntity($row);
        } catch (\Exception $e) {
            $this->logError('Could not delete UserCard: ' . $e->getMessage());
            return false;
        }

        if ($row->getCatUsername() == $user->getCatUsername()) {
            // Activate another card (if any) or remove cat_username and cat_password
            $cards = $this->getLibraryCards($user);
            if (count($cards) > 0) {
                $this->activateLibraryCard($user, current($cards)->getId());
            } else {
                $user->setCatUsername(null);
                $user->setRawCatPassword(null);
                $user->setCatPassEnc(null);
                $this->persistEntity($user);
            }
        }

        return true;
    }

    /**
     * Persist the provided library card data, either by updating a specified card
     * or by creating a new one (when $card is null). Also updates the primary user
     * row when appropriate. Will throw an exception if a duplicate $username value
     * is provided; there should only be one card row per username.
     *
     * Returns the row that was added or updated.
     *
     * @param UserEntityInterface|int          $userOrId User object or identifier
     * @param UserCardEntityInterface|int|null $cardOrId Card entity or ID (null = create new)
     * @param string                           $cardName Card name
     * @param string                           $username Username
     * @param string                           $password Password
     * @param string                           $homeLib  Home Library
     *
     * @return UserCardEntityInterface
     * @throws \VuFind\Exception\LibraryCard
     */
    public function persistLibraryCardData(
        UserEntityInterface|int $userOrId,
        UserCardEntityInterface|int|null $cardOrId,
        string $cardName,
        string $username,
        string $password,
        string $homeLib = ''
    ): UserCardEntityInterface {
        if (!$this->capabilities->libraryCardsEnabled()) {
            throw new \VuFind\Exception\LibraryCard('Library Cards Disabled');
        }
        // Extract a card ID, if available:
        $id = $cardOrId instanceof UserCardEntityInterface ? $cardOrId->getId() : $cardOrId;
        // Check that the username is not already in use in another card
        $usernameCheck = current($this->getLibraryCards($userOrId, catUsername: $username));
        if (!empty($usernameCheck) && ($id === null || $usernameCheck->getId() != $id)) {
            throw new \VuFind\Exception\LibraryCard(
                'Username is already in use in another library card'
            );
        }

        $user = is_int($userOrId)
            ? $this->getDbService(UserServiceInterface::class)->getUserById($userOrId) : $userOrId;

        $row = ($id !== null) ? current($this->getLibraryCards($user, $id)) : null;
        if (empty($row)) {
            $row = $this->createEntity()
                ->setUser($this->getDoctrineReference(UserEntityInterface::class, $user))
                ->setCreated(new DateTime());
        }
        $row->setCardName($cardName);
        $row->setCatUsername($username);
        if (!empty($homeLib)) {
            $row->setHomeLibrary($homeLib);
        }
        if ($this->ilsAuthenticator->passwordEncryptionEnabled()) {
            $row->setRawCatPassword(null);
            $row->setCatPassEnc($this->ilsAuthenticator->encrypt($password));
        } else {
            $row->setRawCatPassword($password);
            $row->setCatPassEnc(null);
        }

        $this->persistEntity($row);

        // If this is the first or active library card, or no credentials are
        // currently set, activate the card now
        if (
            count($this->getLibraryCards($user)) == 1 || !$user->getCatUsername()
            || $user->getCatUsername() === $row->getCatUsername()
        ) {
            $this->activateLibraryCard($user, $row->getId());
        }

        return $row;
    }

    /**
     * Verify that the user's current ILS settings exist in their library card data
     * (if enabled) and are up to date. Designed to be called after updating the
     * user row; will create or modify library card rows as needed.
     *
     * @param UserEntityInterface|int $userOrId User object or identifier
     *
     * @return bool
     * @throws \VuFind\Exception\PasswordSecurity
     */
    public function synchronizeUserLibraryCardData(UserEntityInterface|int $userOrId): bool
    {
        if (!$this->capabilities->libraryCardsEnabled()) {
            return true; // success, because there's nothing to do
        }
        $user = is_int($userOrId)
            ? $this->getDbService(UserServiceInterface::class)->getUserById($userOrId) : $userOrId;
        if (!$user->getCatUsername()) {
            return true; // success, because there's nothing to do
        }
        $cards = $this->getLibraryCards($user, catUsername: $user->getCatUsername());
        if (!($card = reset($cards))) {
            $card = $this->createEntity()
                ->setUser($this->getDoctrineReference(UserEntityInterface::class, $user))
                ->setCatUsername($user->getCatUsername())
                ->setCardName($user->getCatUsername())
                ->setCreated(new DateTime());
        }
        // Always update home library and password
        $card->setHomeLibrary($user->getHomeLibrary());
        $card->setRawCatPassword($user->getRawCatPassword());
        $card->setCatPassEnc($user->getCatPassEnc());

        $this->persistEntity($card);

        // Re-read and verify that there are no duplicates that could have been created with concurrent
        // requests such as a user managing to double-submit a login request:
        $cards = $this->getLibraryCards($user, catUsername: $user->getCatUsername());
        if (count($cards) > 1) {
            // We have duplicates. Leave only the first one on the assumption that it has the correct card
            // name (that could have been previously modified by the user). Plus we just updated it with current
            // home library etc.
            array_shift($cards);
            foreach ($cards as $current) {
                $this->deleteLibraryCard($user, $current);
            }
        }

        return true;
    }

    /**
     * Activate a library card for the given username.
     *
     * @param UserEntityInterface|int $userOrId User owning card
     * @param int                     $id       Library card ID to activate
     *
     * @return void
     * @throws \VuFind\Exception\LibraryCard
     */
    public function activateLibraryCard(UserEntityInterface|int $userOrId, int $id): void
    {
        if (!$this->capabilities->libraryCardsEnabled()) {
            throw new \VuFind\Exception\LibraryCard('Library Cards Disabled');
        }
        $row = $this->getOrCreateLibraryCard($userOrId, $id);
        $user = is_int($userOrId)
            ? $this->getDbService(UserServiceInterface::class)->getUserById($userOrId) : $userOrId;
        $user->setCatUsername($row->getCatUsername());
        $user->setRawCatPassword($row->getRawCatPassword());
        $user->setCatPassEnc($row->getCatPassEnc());
        $user->setHomeLibrary($row->getHomeLibrary());
        $this->persistEntity($user);
    }

    /**
     * Create a UserCard entity object.
     *
     * @return UserCardEntityInterface
     */
    public function createEntity(): UserCardEntityInterface
    {
        return $this->entityPluginManager->get(UserCardEntityInterface::class);
    }
}
