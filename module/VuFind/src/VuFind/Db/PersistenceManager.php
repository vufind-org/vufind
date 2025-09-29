<?php

/**
 * Class to manage database persistence operations.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @package  Db
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db;

use Doctrine\ORM\EntityManagerInterface;
use VuFind\Auth\UserSessionPersistenceInterface;
use VuFind\Db\Entity\EntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;
use VuFind\Exception\DuplicateKeyException;

/**
 * Class to manage database persistence operations.
 *
 * @category VuFind
 * @package  Db
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class PersistenceManager implements DbServiceAwareInterface
{
    use DbServiceAwareTrait;

    /**
     * Constructor
     *
     * @param EntityManagerInterface $entityManager Doctrine ORM entity manager
     * @param bool                   $privacy       Is user privacy mode enabled?
     */
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected bool $privacy = false
    ) {
    }

    /**
     * Persist an entity.
     *
     * @param EntityInterface $entity Entity to persist.
     *
     * @return void
     */
    public function persistEntity(EntityInterface $entity): void
    {
        if ($this->privacy && $entity instanceof UserEntityInterface) {
            $this->getDbService(UserSessionPersistenceInterface::class)->addUserDataToSession($entity);
            return;
        }
        try {
            $this->entityManager->persist($entity);
            $this->entityManager->flush();
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            throw $this->exceptionIndicatesDuplicateKey($e)
                ? new DuplicateKeyException($e->getMessage(), $e->getCode(), $e)
                : $e;
        }
    }

    /**
     * Does the provided exception indicate that a duplicate key value has been
     * created?
     *
     * @param \Exception $e Exception to check
     *
     * @return bool
     */
    protected function exceptionIndicatesDuplicateKey(\Exception $e): bool
    {
        return strstr($e->getMessage(), 'Duplicate entry') !== false;
    }

    /**
     * Delete an entity.
     *
     * @param EntityInterface $entity Entity to persist.
     *
     * @return void
     */
    public function deleteEntity(EntityInterface $entity): void
    {
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
