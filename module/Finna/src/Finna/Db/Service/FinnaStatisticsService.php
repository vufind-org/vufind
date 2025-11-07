<?php

/**
 * Database service for Finna statistics.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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

namespace Finna\Db\Service;

use Finna\Db\Entity\FinnaPageViewStatsEntityInterface;
use Finna\Db\Entity\FinnaRecordStatsLog;
use Finna\Db\Entity\FinnaRecordStatsLogEntityInterface;
use Finna\Db\Entity\FinnaRecordViewEntityInterface;
use Finna\Db\Entity\FinnaRecordViewInstitutionViewEntityInterface;
use Finna\Db\Entity\FinnaRecordViewRecordEntityInterface;
use Finna\Db\Entity\FinnaRecordViewRecordFormat;
use Finna\Db\Entity\FinnaRecordViewRecordFormatEntityInterface;
use Finna\Db\Entity\FinnaRecordViewRecordRights;
use Finna\Db\Entity\FinnaRecordViewRecordRightsEntityInterface;
use Finna\Db\Entity\FinnaSessionStatsEntityInterface;
use VuFind\Db\Service\AbstractDbService;

/**
 * Database service for Finna statistics.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class FinnaStatisticsService extends AbstractDbService implements
    FinnaStatisticsServiceInterface
{
    /**
     * Formats cache for detailed record views
     *
     * @var FinnaRecordViewRecordFormatEntityInterface[]
     */
    protected $formatCache = [];

    /**
     * Usage rights cache for detailed record views
     *
     * @var FinnaRecordViewRecordRightsEntityInterface[]
     */
    protected $usageRightsCache = [];

    /**
     * Cache for a view record
     *
     * @var ?FinnaRecordViewRecordEntityInterface
     */
    protected ?FinnaRecordViewRecordEntityInterface $cachedViewRecord = null;

    /**
     * Cache for an institution+view
     *
     * @var ?FinnaRecordViewInstitutionViewEntityInterface
     */
    protected ?FinnaRecordViewInstitutionViewEntityInterface $cachedViewInstView = null;

    /**
     * Create a new session stats entity
     *
     * @return FinnaSessionStatsEntityInterface
     */
    public function createSessionEntity(): FinnaSessionStatsEntityInterface
    {
        return $this->entityPluginManager->get(FinnaSessionStatsEntityInterface::class);
    }

    /**
     * Create a new page view entity
     *
     * @return FinnaPageViewStatsEntityInterface
     */
    public function createPageViewEntity(): FinnaPageViewStatsEntityInterface
    {
        return $this->entityPluginManager->get(FinnaPageViewStatsEntityInterface::class);
    }

    /**
     * Create a new record stats log entity
     *
     * @return FinnaRecordStatsLogEntityInterface
     */
    public function createRecordStatsLogEntity(): FinnaRecordStatsLogEntityInterface
    {
        return $this->entityPluginManager->get(FinnaRecordStatsLogEntityInterface::class);
    }

    /**
     * Get a batch of log entries to process from finna_record_stats_log table
     *
     * @param int $batchSize Number of records to retrieve
     *
     * @return FinnaRecordStatsLogEntityInterface[]
     */
    public function getRecordStatsLogEntriesToProcess(int $batchSize): array
    {
        $dql = 'SELECT l FROM ' . FinnaRecordStatsLog::class . ' l WHERE l.date < :date';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameter('date', date('Y-m-d'));
        return $query->getResult();
    }

    /**
     * Delete a record stats log entry
     *
     * @param FinnaRecordStatsLogEntityInterface $entry Log entry
     *
     * @return void
     */
    public function deleteRecordStatsLogEntry(FinnaRecordStatsLogEntityInterface $entry): void
    {
        $this->deleteEntity($entry);
    }

    /**
     * Add a new session entry
     *
     * @param FinnaSessionStatsEntityInterface $session Session
     *
     * @return void
     */
    public function addSession(FinnaSessionStatsEntityInterface $session): void
    {
        $params = [
            'institution' => $session->getInstitution(),
            'view' => $session->getView(),
            'crawler' => $session->getType()->value,
            'date' => $session->getDate(),
        ];

        $this->processAdd(FinnaSessionStatsEntityInterface::class, $params);
    }

    /**
     * Add a page view
     *
     * @param FinnaPageViewStatsEntityInterface $pageView Page view
     *
     * @return void
     */
    public function addPageView(FinnaPageViewStatsEntityInterface $pageView): void
    {
        $params = [
            'institution' => $pageView->getInstitution(),
            'view' => $pageView->getView(),
            'crawler' => $pageView->getType()->value,
            'date' => $pageView->getDate(),
            'controller' => $pageView->getController(),
            'action' => $pageView->getAction(),
        ];

        $this->processAdd(FinnaPageViewStatsEntityInterface::class, $params);
    }

    /**
     * Add a record view entry from a log entry
     *
     * @param FinnaRecordStatsLogEntityInterface $logEntry Log entry
     *
     * @return void
     */
    public function addRecordView(FinnaRecordStatsLogEntityInterface $logEntry): void
    {
        $params = [
            'inst_view_id' => $this->getRecordViewInstViewByLogEntry($logEntry)->getId(),
            'crawler' => $logEntry->getType()->value,
            'date' => $logEntry->getDate(),
            'record_id' => $this->getRecordViewRecordByLogEntry($logEntry)->getId(),
        ];

        $this->processAdd(FinnaRecordViewEntityInterface::class, $params);
    }

    /**
     * Add a record stats log entry (a detailed entry for processing later via addDetailedRecordView)
     *
     * @param FinnaRecordStatsLogEntityInterface $logEntry Log entry
     *
     * @return void
     */
    public function addRecordStatsLogEntry(FinnaRecordStatsLogEntityInterface $logEntry): void
    {
        $params = [
            'institution' => $logEntry->getInstitution(),
            'view' => $logEntry->getView(),
            'crawler' => $logEntry->getType()->value,
            'date' => $logEntry->getDate(),
            'backend' => $logEntry->getBackend(),
            'source' => $logEntry->getSource(),
            'record_id' => $logEntry->getRecordId(),
            'formats' => $logEntry->getFormats(),
            'usage_rights' => $logEntry->getUsageRights(),
            'online'  => $logEntry->getOnline() ? 1 : 0,
            'extra_metadata' => $logEntry->getExtraMetadata(),
        ];

        $this->processAdd(FinnaRecordStatsLogEntityInterface::class, $params);
    }

    /**
     * Add a detailed record view entry from a log entry
     *
     * Note: This is a relatively slow and complex function and should only be
     * executed from a batch processing utility
     *
     * @param FinnaRecordStatsLogEntityInterface $logEntry Log entry
     *
     * @return void
     */
    public function addDetailedRecordView(FinnaRecordStatsLogEntityInterface $logEntry): void
    {
        $params = [
            'inst_view_id' => $this->getRecordViewInstViewByLogEntry($logEntry)->getId(),
            'crawler' => $logEntry->getType()->value,
            'date' => $logEntry->getDate(),
            'record_id' => $this->getRecordViewRecordByLogEntry($logEntry)->getId(),
        ];

        $this->processAdd(FinnaRecordViewEntityInterface::class, $params);
    }

    /**
     * Get a record view format entity by id
     *
     * @param int $id Id
     *
     * @return FinnaRecordViewRecordFormatEntityInterface
     */
    public function getRecordViewRecordFormatById(int $id): FinnaRecordViewRecordFormatEntityInterface
    {
        return $this->getEntityById(FinnaRecordViewRecordFormat::class, $id);
    }

    /**
     * Get a record view format entity by id
     *
     * @param int $id Id
     *
     * @return FinnaRecordViewRecordRightsEntityInterface
     */
    public function getRecordViewRecordUsageRightsById(int $id): FinnaRecordViewRecordRightsEntityInterface
    {
        return $this->getEntityById(FinnaRecordViewRecordRights::class, $id);
    }

    /**
     * Get a record view record by log entry
     *
     * @param FinnaRecordStatsLogEntityInterface $logEntry Log entry
     *
     * @return FinnaRecordViewRecordEntityInterface
     */
    public function getRecordViewRecordByLogEntry(
        FinnaRecordStatsLogEntityInterface $logEntry
    ): FinnaRecordViewRecordEntityInterface {
        if (
            null !== $this->cachedViewRecord
            && $this->cachedViewRecord->getBackend() === $logEntry->getBackend()
            && $this->cachedViewRecord->getSource() === $logEntry->getSource()
            && $this->cachedViewRecord->getRecordId() === $logEntry->getRecordId()
        ) {
            return $this->cachedViewRecord;
        }

        $dql = 'SELECT r FROM ' . FinnaRecordViewRecordEntityInterface::class . ' r'
            . ' WHERE r.backend = :backend AND r.source = :source AND r.recordId = :recordId';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters([
            'backend' => $logEntry->getBackend(),
            'source' => $logEntry->getSource(),
            'recordId' => $logEntry->getRecordId(),
        ]);
        if (!($entity = $query->getOneOrNullResult())) {
            $entity = $this->entityPluginManager->get(FinnaRecordViewRecordEntityInterface::class);
        }
        $entity
            ->setBackend($logEntry->getBackend())
            ->setSource($logEntry->getSource())
            ->setRecordId($logEntry->getRecordId())
            ->setFormat($this->getRecordViewRecordFormatFromString($logEntry->getFormats()))
            ->setUsageRights($this->getRecordViewRecordRightsFromString($logEntry->getUsageRights()))
            ->setOnline($logEntry->getOnline())
            ->setExtraMetadata($logEntry->getExtraMetadata());
        $this->persistEntity($entity);
        $this->cachedViewRecord = $entity;
        return $entity;
    }

    /**
     * Get record view record format entity from string
     *
     * @param string $format Format
     *
     * @return FinnaRecordViewRecordFormatEntityInterface
     */
    protected function getRecordViewRecordFormatFromString(string $format): FinnaRecordViewRecordFormatEntityInterface
    {
        if (!isset($this->formatCache[$format])) {
            $dql = 'SELECT f FROM ' . FinnaRecordViewRecordFormatEntityInterface::class . ' f'
                . ' WHERE f.formats = :formats';
            $query = $this->entityManager->createQuery($dql)
                ->setParameter('formats', $format);
            if (!($entity = $query->getOneOrNullResult())) {
                $entity = $this->entityPluginManager->get(FinnaRecordViewRecordFormatEntityInterface::class);
                $entity->setFormats($format);
            }
            $this->formatCache[$format] = $entity;
        }
        return $this->formatCache[$format];
    }

    /**
     * Get record view record rights entity from string
     *
     * @param string $rights Rights
     *
     * @return FinnaRecordViewRecordRightsEntityInterface
     */
    protected function getRecordViewRecordRightsFromString(string $rights): FinnaRecordViewRecordRightsEntityInterface
    {
        if (!isset($this->usageRightsCache[$rights])) {
            $dql = 'SELECT r FROM ' . FinnaRecordViewRecordRightsEntityInterface::class . ' r'
                . ' WHERE r.rights = :rights';
            $query = $this->entityManager->createQuery($dql)
                ->setParameter('rights', $rights);
            if (!($entity = $query->getOneOrNullResult())) {
                $entity = $this->entityPluginManager->get(FinnaRecordViewRecordRightsEntityInterface::class);
                $entity->setRights($rights);
            }
            $this->usageRightsCache[$rights] = $entity;
        }
        return $this->usageRightsCache[$rights];
    }

    /**
     * Get FinnaRecordViewInstitutionView for a log entry
     *
     * @param FinnaRecordStatsLogEntityInterface $logEntry Log entry
     *
     * @return FinnaRecordViewInstitutionViewEntityInterface
     */
    protected function getRecordViewInstViewByLogEntry(
        FinnaRecordStatsLogEntityInterface $logEntry
    ): FinnaRecordViewInstitutionViewEntityInterface {
        if (
            null !== $this->cachedViewInstView
            && $this->cachedViewInstView->getInstitution() === $logEntry->getInstitution()
            && $this->cachedViewInstView->getView() === $logEntry->getView()
        ) {
            return $this->cachedViewInstView;
        }

        $dql = 'SELECT iv FROM ' . FinnaRecordViewInstitutionViewEntityInterface::class . ' iv'
            . ' WHERE iv.institution = :institution AND iv.view = :view';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters([
            'institution' => $logEntry->getInstitution(),
            'view' => $logEntry->getView(),
        ]);
        $entity = $query->getOneOrNullResult();
        if (!$entity) {
            $entity = $this->entityPluginManager->get(FinnaRecordViewInstitutionViewEntityInterface::class);
            $entity->setInstitution($logEntry->getInstitution());
            $entity->setView($logEntry->getView());
            $this->persistEntity($entity);
        }
        $this->cachedViewInstView = $entity;
        return $entity;
    }

    /**
     * Add or update a statistics table entry
     *
     * @param string $entityClass Entity class
     * @param array  $params      Columns
     *
     * @return void
     *
     * @throws \Exception
     */
    protected function processAdd(string $entityClass, array $params): void
    {
        // Use direct DBAL access to handle the addition. This allows us to do an 'UPSERT' and prevents any issues
        // that could be caused by unique constraint violations that would close the entity manager:
        $metadata = $this->entityManager->getClassMetadata($entityClass);
        $table = $metadata->getTableName();
        $placeholders = array_map(fn ($s) => ":$s", array_keys($params));
        $sql = "INSERT INTO $table ("
            . implode(',', array_keys($params))
            . ') VALUES (' . implode(',', $placeholders) . ')'
            . ' ON DUPLICATE KEY UPDATE count=count+1';

        $conn = $this->entityManager->getConnection();
        $conn->executeQuery($sql, $params);
    }
}
