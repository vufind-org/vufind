<?php

/**
 * Database driver for statistics
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @package  Statistics
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\Statistics\Driver;

use Finna\Db\Service\FinnaStatisticsServiceInterface;
use Finna\Db\Type\FinnaStatisticsClientType;
use Psr\Log\LoggerAwareInterface;
use VuFind\Log\LoggerAwareTrait;

/**
 * Database driver for statistics
 *
 * @category VuFind
 * @package  Statistics
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Database implements DriverInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Constructor
     *
     * @param FinnaStatisticsServiceInterface $statisticsService Statistics database service
     */
    public function __construct(protected FinnaStatisticsServiceInterface $statisticsService)
    {
    }

    /**
     * Add a new session to statistics
     *
     * @param string $institution Institution code
     * @param string $view        View subpath (empty string for default view)
     * @param int    $type        Request type bitmap
     * @param array  $session     Session data
     *
     * @return void
     */
    public function addNewSession(
        string $institution,
        string $view,
        int $type,
        array $session
    ): void {
        $session = $this->statisticsService->createSessionEntity()
            ->setInstitution($institution)
            ->setView($view)
            ->setDate(date('Y-m-d'))
            ->setType(FinnaStatisticsClientType::from($type));
        $this->statisticsService->addSession($session);
    }

    /**
     * Add a page view to statistics
     *
     * @param string $institution Institution code
     * @param string $view        View subpath (empty string for default view)
     * @param int    $type        Request type bitmap
     * @param string $controller  Controller
     * @param string $action      Action
     *
     * @return void
     */
    public function addPageView(
        string $institution,
        string $view,
        int $type,
        string $controller,
        string $action
    ): void {
        $pageView = $this->statisticsService->createPageViewEntity()
            ->setInstitution($institution)
            ->setView($view)
            ->setDate(date('Y-m-d'))
            ->setType(FinnaStatisticsClientType::from($type))
            ->setController($controller)
            ->setAction($action);
        $this->statisticsService->addPageView($pageView);
    }

    /**
     * Add a record view to statistics
     *
     * @param string $institution Institution code
     * @param string $view        View subpath (empty string for default view)
     * @param int    $type        Request type bitmap
     * @param string $backend     Backend ID
     * @param string $source      Record source
     * @param string $recordId    Record ID
     * @param array  $formats     Record formats
     * @param array  $rights      Record usage rights
     * @param int    $online      Whether the record is available online (0 = no,
     * 1 = yes, 2 = freely)
     *
     * @return void
     */
    public function addRecordView(
        string $institution,
        string $view,
        int $type,
        string $backend,
        string $source,
        string $recordId,
        array $formats,
        array $rights,
        int $online
    ): void {
        $recordView = $this->statisticsService->createRecordStatsLogEntity()
            ->setInstitution($institution)
            ->setView($view)
            ->setDate(date('Y-m-d'))
            ->setType(FinnaStatisticsClientType::from($type))
            ->setBackend($backend)
            ->setSource($source)
            ->setRecordId($recordId)
            ->setFormats(implode('|', $formats))
            ->setUsageRights(implode('|', $rights))
            ->setOnline($online)
            ->setExtraMetadata(null);
        $this->statisticsService->addRecordView($recordView);
        $this->statisticsService->addRecordStatsLogEntry($recordView);
    }
}
