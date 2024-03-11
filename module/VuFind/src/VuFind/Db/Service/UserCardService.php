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
use VuFind\Db\Entity\User;
use VuFind\Db\Entity\UserCard;
use VuFind\Log\LoggerAwareTrait;

use function is_int;

/**
 * Database service for UserCard.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class UserCardService extends AbstractDbService implements LoggerAwareInterface, ServiceAwareInterface
{
    use LoggerAwareTrait;
    use \VuFind\Db\Service\ServiceAwareTrait;

    /**
     * Get user_card rows with insecure catalog passwords
     *
     * @return array
     */
    public function getInsecureRows()
    {
        $dql = 'SELECT UC FROM ' . $this->getEntityClass(UserCard::class)
            . ' UC WHERE UC.catPassword IS NOT NULL';
        $query = $this->entityManager->createQuery($dql);
        return $query->getResult();
    }

    /**
     * Get user_card rows with catalog usernames set
     *
     * @return array
     */
    public function getAllRowsWithUsernames()
    {
        $dql = 'SELECT UC FROM ' . $this->getEntityClass(UserCard::class)
            . ' UC WHERE UC.catUsername IS NOT NULL';
        $query = $this->entityManager->createQuery($dql);
        return $query->getResult();
    }

    /**
     * Get all library cards associated with the user.
     *
     * @param int|User $user        User object or identifier
     * @param ?int     $id          UserCard id
     * @param ?string  $catUsername CatUsername
     *
     * @return array
     */
    public function getLibraryCards($user, $id = null, $catUsername = null)
    {
        $dql = 'SELECT UC '
        . 'FROM ' . $this->getEntityClass(UserCard::class) . ' UC ';
        $dqlWhere = ['UC.user = :user'];
        $parameters['user'] = $user;
        if (null !== $id) {
            $dqlWhere[] = 'UC.id = :id';
            $parameters['id'] = $id;
        }
        if (null !== $catUsername) {
            $dqlWhere[] = 'UC.catUsername = :catUsername';
            $parameters['catUsername'] = $catUsername;
        }
        $dql .= ' WHERE ' . implode(' AND ', $dqlWhere);
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $records = $query->getResult();
        return $records;
    }

    /**
     * Get library card data
     *
     * @param int|User $user User object or identifier
     * @param int      $id   Library card ID
     *
     * @return UserCard|false Card data if found, false otherwise
     * @throws \VuFind\Exception\LibraryCard
     */
    public function getLibraryCard($user, $id = null)
    {
        if ($id === null) {
            if (is_int($user)) {
                $user = $this->getDbService(\VuFind\Db\Service\UserService::class)
                    ->getUserById($user);
            }

            $row = $this->createEntity()
                ->setCardName('')
                ->setUser($user)
                ->setCatUsername('')
                ->setRawCatPassword('');
        } else {
            $row = current($this->getLibraryCards($user, $id));
            if ($row === false) {
                throw new \VuFind\Exception\LibraryCard('Library Card Not Found');
            }
        }
        return $row;
    }

    /**
     * Delete library card
     *
     * @param ?UserCard $userCard UserCard to be deleted
     *
     * @return bool
     * @throws \VuFind\Exception\LibraryCard
     */
    public function deleteLibraryCard($userCard)
    {
        if (empty($userCard)) {
            throw new \Exception('Library card not found');
        }

        try {
            $this->deleteEntity($userCard);
        } catch (\Exception $e) {
            $this->logError('Could not delete UserCard: ' . $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Save library card with the given information
     *
     * @param int|User $user     User object or identifier
     * @param int      $id       Card ID
     * @param string   $cardName Card name
     * @param string   $username Username
     * @param string   $password Password
     * @param string   $homeLib  Home Library
     *
     * @return UserCard|false
     * @throws \VuFind\Exception\LibraryCard
     */
    public function saveLibraryCard(
        $user,
        $id,
        $cardName,
        $username,
        $password,
        $homeLib = ''
    ) {
        $userService = $this->getDbService(\VuFind\Db\Service\UserService::class);
        if (is_int($user)) {
            $user = $userService->getUserById($user);
        }
        $userCard = current($this->getLibraryCards($user, null, $username));
        if (!empty($userCard) && ($id === null || $userCard->getId() != $id)) {
            throw new \VuFind\Exception\LibraryCard(
                'Username is already in use in another library card'
            );
        }
        $userCard = null;
        if ($id !== null) {
            $userCard = current($this->getLibraryCards($user, $id));
        }
        if (empty($userCard)) {
            $userCard = $this->createEntity()
                ->setUser($user)
                ->setCreated(new \DateTime());
        }
        $userCard->setCardName($cardName);
        $userCard->setCatUsername($username);
        if (!empty($homeLib)) {
            $userCard->setHomeLibrary($homeLib);
        }

        if ($userService->passwordEncryptionEnabled()) {
            $userCard->setRawCatPassword(null);
            $userCard->setCatPassEnc($userService->encrypt($password));
        } else {
            $userCard->setRawCatPassword($password);
            $userCard->setCatPassEnc(null);
        }
        try {
            $this->persistEntity($userCard);
        } catch (\Exception $e) {
            $this->logError('Could not save UserCard: ' . $e->getMessage());
            return false;
        }

        return $userCard;
    }

    /**
     * Verify that the current card information exists in user's library cards
     * (if enabled) and is up to date.
     *
     * @param int|User $user User object or identifier
     *
     * @return bool
     * @throws \VuFind\Exception\PasswordSecurity
     */
    public function updateLibraryCardEntry($user)
    {
        if (is_int($user)) {
            $user = $this->getDbService(\VuFind\Db\Service\UserService::class)
                ->getUserById($user);
        }

        $userCard = current($this->getLibraryCards($user->getId(), null, $user->getCatUsername()));
        if (empty($userCard)) {
            $userCard = $this->createEntity()
                ->setUser($user)
                ->setCatUsername($user->getCatUsername())
                ->setCardName($user->getCatUsername())
                ->setCreated(new \DateTime());
        }
        $userCard->setHomeLibrary($user->getHomeLibrary())
            ->setRawCatPassword($user->getRawCatPassword())
            ->setCatPassEnc($user->getCatPassEnc());
        try {
            $this->persistEntity($userCard);
        } catch (\Exception $e) {
            $this->logError('Could not update UserCard: ' . $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Create a UserCard entity object.
     *
     * @return UserCard
     */
    public function createEntity(): UserCard
    {
        $class = $this->getEntityClass(UserCard::class);
        return new $class();
    }
}
