<?php

/**
 * Database service for event table.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use DateTime;
use Doctrine\ORM\EntityManager;
use VuFind\Db\Entity\AuditEventEntityInterface;
use VuFind\Db\Entity\PaymentEntityInterface;
use VuFind\Db\Entity\PluginManager as EntityPluginManager;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\PersistenceManager;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\AuditEventType;

use function in_array;
use function is_string;

/**
 * Database service for event table.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class AuditEventService extends AbstractDbService implements
    AuditEventServiceInterface,
    Feature\DeleteExpiredInterface
{
    /**
     * Constructor
     *
     * @param EntityManager       $entityManager       Doctrine ORM entity manager
     * @param EntityPluginManager $entityPluginManager Database entity plugin manager
     * @param PersistenceManager  $persistenceManager  Entity persistence manager
     * @param array               $enabledEventTypes   Event types enabled in configuration
     * @param ?string             $sessionId           Session ID (if applicable)
     * @param ?string             $clientIp            Client IP address (if applicable)
     * @param ?string             $serverIp            Server IP address (if applicable)
     * @param ?string             $serverName          Server name (if applicable)
     * @param ?string             $requestUri          Request URI (if applicable)
     */
    public function __construct(
        protected EntityManager $entityManager,
        protected EntityPluginManager $entityPluginManager,
        protected PersistenceManager $persistenceManager,
        protected array $enabledEventTypes,
        protected ?string $sessionId,
        protected ?string $clientIp,
        protected ?string $serverIp,
        protected ?string $serverName,
        protected ?string $requestUri
    ) {
    }

    /**
     * Create an event entity object.
     *
     * @return AuditEventEntityInterface
     */
    public function createEntity(): AuditEventEntityInterface
    {
        return $this->entityPluginManager->get(AuditEventEntityInterface::class);
    }

    /**
     * Add an event.
     *
     * @param AuditEventType|string    $type    Event type
     * @param AuditEventSubtype|string $subtype Event subtype
     * @param ?UserEntityInterface     $user    User
     * @param string                   $message Status message
     * @param array                    $data    Additional data
     *
     * @return void
     */
    public function addEvent(
        AuditEventType|string $type,
        AuditEventSubtype|string $subtype,
        ?UserEntityInterface $user = null,
        ?string $message = null,
        array $data = []
    ): void {
        $type = is_string($type) ? $type : $type->value;
        if (!in_array($type, $this->enabledEventTypes)) {
            return;
        }
        $data = $this->scrubSecrets($data);
        $data['__method'] = $this->getCallerOfParentMethod();
        $event = $this->createEntity();
        $event
            ->setType($type)
            ->setSubtype($subtype)
            ->setUser($user)
            ->setSessionId($this->sessionId)
            ->setClientIp($this->clientIp)
            ->setServerIp($this->serverIp)
            ->setServerName($this->serverName)
            ->setMessage($message)
            ->setData(json_encode($data));
        $this->persistEntity($event);
    }

    /**
     * Add a payment event.
     *
     * @param PaymentEntityInterface   $payment Payment
     * @param AuditEventSubtype|string $subtype Event subtype
     * @param string                   $message Status message
     * @param array                    $data    Additional data
     *
     * @return void
     */
    public function addPaymentEvent(
        PaymentEntityInterface $payment,
        AuditEventSubtype|string $subtype,
        string $message = '',
        array $data = []
    ): void {
        $type = AuditEventType::Payment->value;
        if (!in_array($type, $this->enabledEventTypes)) {
            return;
        }
        $data = $this->scrubSecrets($data);
        $data['__method'] = $this->getCallerOfParentMethod();
        $data['__request_uri'] = $this->requestUri;
        $event = $this->createEntity();
        $event
            ->setType($type)
            ->setSubtype($subtype)
            ->setUser($payment->getUser())
            ->setPayment($payment)
            ->setSessionId($this->sessionId)
            ->setClientIp($this->clientIp)
            ->setServerIp($this->serverIp)
            ->setServerName($this->serverName)
            ->setMessage($message)
            ->setData(json_encode($data));
        $this->persistEntity($event);
    }

    /**
     * Get an array of events.
     *
     * @param ?DateTime                     $fromDate   Start date
     * @param ?DateTime                     $untilDate  End date
     * @param AuditEventType|string|null    $type       Event type
     * @param AuditEventSubtype|string|null $subtype    Event subtype
     * @param UserEntityInterface|int|null  $userOrId   User entity or ID of user
     * @param ?string                       $username   User's username
     * @param ?string                       $clientIp   Client's IP address
     * @param ?string                       $serverIp   Server's IP address
     * @param ?string                       $serverName Server's host name
     * @param ?PaymentEntityInterface       $payment    Payment entity
     * @param ?array                        $sort       Sort order (null for default descending order)
     *
     * @return AuditEventEntityInterface[]
     */
    public function getEvents(
        ?DateTime $fromDate = null,
        ?DateTime $untilDate = null,
        AuditEventType|string|null $type = null,
        AuditEventSubtype|string|null $subtype = null,
        UserEntityInterface|int|null $userOrId = null,
        ?string $username = null,
        ?string $clientIp = null,
        ?string $serverIp = null,
        ?string $serverName = null,
        ?PaymentEntityInterface $payment = null,
        ?array $sort = null,
    ): array {
        $user = $userOrId instanceof UserEntityInterface ? $userOrId->getId() : $userOrId;
        if (null === $sort) {
            $sort = ['date DESC', 'id DESC'];
        }

        $dql = 'SELECT e FROM ' . AuditEventEntityInterface::class . ' e';
        $conditions = [];
        $params = [];

        // Handle date limits:
        if (null !== $fromDate) {
            $conditions[] = 'e.date >= :fromDate';
            $params['fromDate'] = $fromDate->format(VUFIND_DATABASE_DATETIME_FORMAT);
        }
        if (null !== $untilDate) {
            $conditions[] = 'e.date <= :untilDate';
            $params['untilDate'] = $untilDate->format(VUFIND_DATABASE_DATETIME_FORMAT);
        }
        if (null !== $type) {
            $conditions[] = 'e.type = :type';
            $params['type'] = is_string($type) ? $type : $type->value;
        }
        if (null !== $subtype) {
            $conditions[] = 'e.subtype = :subtype';
            $params['subtype'] = is_string($subtype) ? $subtype : $subtype->value;
        }
        if (null !== $user) {
            $conditions[] = 'e.user = :user';
            $params['user'] = $user;
        }
        if (null !== $username) {
            $conditions[] = 'e.username = :username';
            $params['username'] = $username;
        }
        if (null !== $clientIp) {
            $conditions[] = 'e.clientIp = :clientIp';
            $params['clientIp'] = $clientIp;
        }
        if (null !== $serverIp) {
            $conditions[] = 'e.serverIp = :serverIp';
            $params['serverIp'] = $serverIp;
        }
        if (null !== $serverName) {
            $conditions[] = 'e.serverName = :serverName';
            $params['serverName'] = $serverName;
        }
        if (null !== $payment) {
            $conditions[] = 'e.payment = :payment';
            $params['payment'] = $payment->getId();
        }

        if ($conditions) {
            $dql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        if ($sort) {
            $sortFields = array_map(
                fn ($s) => "e.$s",
                $sort
            );
            $dql .= ' ORDER BY ' . implode(', ', $sortFields);
        }

        return $this->entityManager
            ->createQuery($dql)
            ->setParameters($params)
            ->getResult();
    }

    /**
     * Delete expired records. Allows setting a limit so that rows can be deleted in small batches.
     *
     * @param DateTime $dateLimit Date threshold of an "expired" record.
     * @param ?int     $limit     Maximum number of rows to delete or null for no limit.
     *
     * @return int Number of rows deleted
     */
    public function deleteExpired(DateTime $dateLimit, ?int $limit = null): int
    {
        $subQueryBuilder = $this->entityManager->createQueryBuilder();
        $subQueryBuilder->select('a.id')
            ->from(AuditEventEntityInterface::class, 'a')
            ->where('a.date < :latestDate')
            ->setParameter('latestDate', $dateLimit->format(VUFIND_DATABASE_DATETIME_FORMAT));
        if ($limit) {
            $subQueryBuilder->setMaxResults($limit);
        }
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->delete(AuditEventEntityInterface::class, 'a')
            ->where('a.id IN (:ids)')
            ->setParameter('ids', $subQueryBuilder->getQuery()->getResult());
        return $queryBuilder->getQuery()->execute();
    }

    /**
     * Purge all events from the database.
     *
     * @return void
     */
    public function purgeEvents(): void
    {
        $this->entityManager->createQuery('DELETE ' . AuditEventEntityInterface::class . ' e')
            ->execute();
    }

    /**
     * Get the method that called the parent method here.
     *
     * @return string
     */
    protected function getCallerOfParentMethod(): string
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $methodParts = [$backtrace[2]['class'] ?? '', $backtrace[2]['function'] ?? ''];
        return implode('::', array_filter($methodParts));
    }

    /**
     * Remove any secrets from details to be logged.
     *
     * @param array $details Details
     *
     * @return @rray
     */
    protected function scrubSecrets(array $details): array
    {
        array_walk_recursive(
            $details,
            function (&$value, $key) {
                if ('csrf' === $key || str_contains($key, 'password')) {
                    $value = '***';
                }
            }
        );
        return $details;
    }
}
