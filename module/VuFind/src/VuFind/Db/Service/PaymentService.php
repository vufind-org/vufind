<?php

/**
 * Database service for payment transactions.
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

declare(strict_types=1);

namespace VuFind\Db\Service;

use DateInterval;
use DateTime;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use DoctrineORMModule\Paginator\Adapter\DoctrinePaginator as DoctrinePaginatorAdapter;
use Laminas\Paginator\Paginator;
use VuFind\Db\Entity\PaymentEntityInterface;
use VuFind\Db\Feature\DateTimeTrait;
use VuFind\Db\Type\PaymentStatus;

use function intval;

/**
 * Database service for payment transactions.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class PaymentService extends AbstractDbService implements PaymentServiceInterface
{
    use DateTimeTrait;

    /**
     * Create a Payment entity object.
     *
     * @return PaymentEntityInterface
     */
    public function createEntity(): PaymentEntityInterface
    {
        return $this->entityPluginManager->get(PaymentEntityInterface::class);
    }

    /**
     * Create a Payment entity object with "in progress" status.
     *
     * @return PaymentEntityInterface
     */
    public function createInProgressPayment(): PaymentEntityInterface
    {
        $entity = $this->createEntity();
        $entity->setCreated(new DateTime());
        $entity->setStatus(PaymentStatus::InProgress);
        return $entity;
    }

    /**
     * Retrieve a payment object.
     *
     * @param int $id Numeric ID for existing payment.
     *
     * @return ?PaymentEntityInterface
     */
    public function getPaymentById(int $id): ?PaymentEntityInterface
    {
        return $this->entityManager->find(PaymentEntityInterface::class, $id);
    }

    /**
     * Get payment by local identifier.
     *
     * @param string $localIdentifier Payment identifier
     *
     * @return ?PaymentEntityInterface
     */
    public function getPaymentByLocalIdentifier(string $localIdentifier): ?PaymentEntityInterface
    {
        $dql = 'SELECT p '
                . 'FROM ' . PaymentEntityInterface::class . ' p '
                . 'WHERE p.localIdentifier = :localIdentifier';
        $parameters = compact('localIdentifier');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        return $query->getOneOrNullResult();
    }

    /**
     * Get last paid payment for a patron
     *
     * @param string $catUsername Patron's catalog username
     *
     * @return ?PaymentEntityInterface
     */
    public function getLastPaidPaymentForPatron(string $catUsername): ?PaymentEntityInterface
    {
        $statuses = [
            PaymentStatus::Completed->value,
            PaymentStatus::Paid->value,
            PaymentStatus::RegistrationFailed->value,
            PaymentStatus::RegistrationExpired->value,
            PaymentStatus::RegistrationResolved->value,
            PaymentStatus::FinesUpdated->value,
        ];

        $dql = 'SELECT p FROM ' . PaymentEntityInterface::class . ' p'
            . ' WHERE p.catUsername = :catUsername AND p.status IN (:statuses)'
            . ' ORDER BY p.paid DESC';
        $parameters = compact('catUsername', 'statuses');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->setMaxResults(1);
        return $query->getOneOrNullResult();
    }

    /**
     * Get latest paid payment that requires registration for the patron.
     *
     * @param string $catUsername Patron's catalog username
     *
     * @return ?PaymentEntityInterface
     */
    public function getPaidPaymentInProgressForPatron(string $catUsername): ?PaymentEntityInterface
    {
        $statuses = [
            PaymentStatus::Paid->value,
            PaymentStatus::RegistrationFailed->value,
            PaymentStatus::RegistrationExpired->value,
            PaymentStatus::FinesUpdated->value,
        ];

        $dql = 'SELECT p FROM ' . PaymentEntityInterface::class . ' p'
            . ' WHERE p.catUsername = :catUsername AND p.status IN (:statuses)'
            . ' ORDER BY p.created DESC';
        $parameters = compact('catUsername', 'statuses');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->setMaxResults(1);
        return $query->getOneOrNullResult();
    }

    /**
     * Get any payment that has been started for the patron, but not progressed further.
     *
     * @param string $catUsername        Patron's catalog username
     * @param int    $paymentMaxDuration Max duration for a payment in minutes
     *
     * @return ?PaymentEntityInterface
     */
    public function getStartedPaymentForPatron(
        string $catUsername,
        int $paymentMaxDuration
    ): ?PaymentEntityInterface {
        $dql = 'SELECT p FROM ' . PaymentEntityInterface::class . ' p'
            . ' WHERE p.catUsername = :catUsername'
            . ' AND p.status = :status'
            . ' AND p.created > :createdLimit'
            . ' ORDER BY p.created DESC';
        $parameters = [
            'catUsername' => $catUsername,
            'status' => PaymentStatus::InProgress->value,
            'createdLimit' => date('Y-m-d H:i:s', time() - $paymentMaxDuration * 60),
        ];
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->setMaxResults(1);
        return $query->getOneOrNullResult();
    }

    /**
     * Get paid payments whose registration failed.
     *
     * @param int $minimumPaidAge How old a paid payment must be (in seconds) for it to be considered failed
     *
     * @return PaymentEntityInterface[]
     */
    public function getFailedPayments(int $minimumPaidAge = 120): array
    {
        $entityClass = PaymentEntityInterface::class;
        $dql = <<<DQL
            SELECT p FROM $entityClass p
              WHERE p.paid > :emptyDate
                AND (
                  p.status = :statusFailed
                  OR
                  (
                    p.status = :statusPaid
                    AND
                    p.paid < :paidLimit
                  )
                )
              ORDER BY p.created
            DQL;
        $parameters = [
            'emptyDate' => $this->getUnassignedDefaultDateTime()->format('Y-m-d H:i:s'),
            'statusFailed' => PaymentStatus::RegistrationFailed->value,
            'statusPaid' => PaymentStatus::Paid->value,
            'paidLimit' => date('Y-m-d H:i:s', time() - $minimumPaidAge),
        ];
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        return $query->getResult();
    }

    /**
     * Get unresolved payments for reporting.
     *
     * @param int $interval Minimum number of minutes since last report was sent.
     *
     * @return PaymentEntityInterface[] payments
     */
    public function getUnresolvedPaymentsToReport(int $interval): array
    {
        $entityClass = PaymentEntityInterface::class;
        $dql = <<<DQL
            SELECT p FROM $entityClass p
              WHERE p.status IN (:statuses)
                AND p.paid > :emptyDate
                AND p.reported < :reportedLimit
              ORDER BY p.created
            DQL;
        $parameters = [
            'statuses' => [PaymentStatus::FinesUpdated->value, PaymentStatus::RegistrationExpired->value],
            'emptyDate' => $this->getUnassignedDefaultDateTime()->format('Y-m-d H:i:s'),
            'reportedLimit' => date('Y-m-d H:i:s', time() - $interval * 60),
        ];
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        return $query->getResult();
    }

    /**
     * Get a filtered list of payments
     *
     * @param PaymentStatus[] $statuses         Payment statuses (optional filter)
     * @param ?string         $localIdentifier  Local identifier (optional filter)
     * @param ?string         $remoteIdentifier Remote identifier (optional filter)
     * @param ?string         $sourceIls        Source ILS (optional filter)
     * @param ?string         $catUsername      ILS username (optional filter)
     * @param ?DateTime       $createdFrom      Beginning of creation date range (optional filter)
     * @param ?DateTime       $createdUntil     End of creation date range (optional filter)
     * @param ?DateTime       $paidFrom         Beginning of payment date range (optional filter)
     * @param ?DateTime       $paidUntil        End of payment date range (optional filter)
     * @param ?int            $page             Current page (optional)
     * @param int             $limit            Limit per page (optional)
     *
     * @return Paginator
     */
    public function getPaymentPaginator(
        array $statuses = [],
        ?string $localIdentifier = null,
        ?string $remoteIdentifier = null,
        ?string $sourceIls = null,
        ?string $catUsername = null,
        ?DateTime $createdFrom = null,
        ?DateTime $createdUntil = null,
        ?DateTime $paidFrom = null,
        ?DateTime $paidUntil = null,
        ?int $page = null,
        int $limit = 20
    ): Paginator {
        $dql = 'SELECT p FROM ' . PaymentEntityInterface::class . ' p';
        $parameters = $dqlWhere = [];

        if ($statuses) {
            $dqlWhere[] = 'p.status IN (:statuses)';
            $parameters['statuses'] = array_map(fn ($s) => $s->value, $statuses);
        }
        if (null !== $localIdentifier) {
            $dqlWhere[] = 'p.localIdentifier LIKE :localIdentifier';
            $parameters['localIdentifier'] = $localIdentifier;
        }
        if (null !== $remoteIdentifier) {
            $dqlWhere[] = 'p.remoteIdentifier LIKE :remoteIdentifier';
            $parameters['remoteIdentifier'] = $remoteIdentifier;
        }
        if (null !== $sourceIls) {
            $dqlWhere[] = 'p.sourceIls LIKE :sourceIls';
            $parameters['sourceIls'] = $sourceIls;
        }
        if (null !== $catUsername) {
            $dqlWhere[] = 'p.catUsername LIKE :catUsername';
            $parameters['catUsername'] = $catUsername;
        }
        if (null !== $createdFrom) {
            $dqlWhere[] = 'p.created >= :createdFrom';
            $parameters['createdFrom'] = $createdFrom;
        }
        if (null !== $createdUntil) {
            $dqlWhere[] = 'p.created < :createdUntil';
            $parameters['createdUntil'] = $createdUntil->add(DateInterval::createFromDateString('1 day'));
        }
        if (null !== $paidFrom) {
            $dqlWhere[] = 'p.paid >= :paidFrom';
            $parameters['paidFrom'] = $paidFrom;
        }
        if (null !== $paidUntil) {
            $dqlWhere[] = 'p.paid < :paidUntil';
            $parameters['paidUntil'] = $paidUntil->add(DateInterval::createFromDateString('1 day'));
        }

        if ($dqlWhere) {
            $dql .= ' WHERE ' . implode(' AND ', $dqlWhere);
        }
        $dql .= ' ORDER BY p.created DESC';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);

        $page = null === $page ? null : intval($page);
        if (null !== $page) {
            $query->setMaxResults($limit);
            $query->setFirstResult($limit * ($page - 1));
        }
        $paginator = new Paginator(new DoctrinePaginatorAdapter(new DoctrinePaginator($query)));
        if (null !== $page) {
            $paginator->setCurrentPageNumber($page);
            $paginator->setItemCountPerPage($limit);
        }
        return $paginator;
    }

    /**
     * Get a list of unique source ILS values.
     *
     * @return array
     */
    public function getUniqueSourceIlsList(): array
    {
        $dql = 'SELECT DISTINCT p.sourceIls FROM ' . PaymentEntityInterface::class
            . ' p ORDER BY p.sourceIls';
        $query = $this->entityManager->createQuery($dql);
        return $query->getSingleColumnResult();
    }

    /**
     * Refresh an entity from the database.
     *
     * @param PaymentEntityInterface $entity Entity to refresh.
     *
     * @return void
     */
    public function refreshEntity(PaymentEntityInterface $entity): void
    {
        $this->entityManager->refresh($entity);
    }
}
