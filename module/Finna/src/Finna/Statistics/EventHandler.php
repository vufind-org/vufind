<?php

/**
 * Statistics event handler
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

namespace Finna\Statistics;

use Finna\Statistics\Driver\DriverInterface;
use VuFind\RecordDriver\AbstractBase as AbstractRecord;

/**
 * Statistics event handler
 *
 * @category VuFind
 * @package  Statistics
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class EventHandler
{
    /**
     * Request type bit indicating a crawler
     *
     * @var int
     */
    public const REQUEST_TYPE_CRAWLER = 1;

    /**
     * Request type bit indicating an API request
     *
     * @var int
     */
    public const REQUEST_TYPE_API = 2;

    /**
     * Request type bit indicating a request from a monitoring system
     *
     * @var int
     */
    public const REQUEST_TYPE_MONITORING = 4;

    /**
     * Institution code
     *
     * @param string
     */
    protected $institution;

    /**
     * View subpath
     *
     * @param string
     */
    protected $view;

    /**
     * Storage driver
     *
     * @var ?DriverInterface
     */
    protected $driver;

    /**
     * User agent
     *
     * @var string
     */
    protected $userAgent;

    /**
     * Whether the request comes from a monitoring system
     *
     * @var bool
     */
    protected $monitoringSystem;

    /**
     * Constructor
     *
     * Note that this must be called before any of the events to be handled is
     *
     * @param string      $institution Institution code
     * @param string      $view        View subpath
     * @param ?BaseDriver $driver      Statistics storage driver
     * @param string      $userAgent   Client's user agent
     * @param bool        $monitoring  Whether the request comes from a monitoring
     * system
     */
    public function __construct(
        string $institution,
        string $view,
        ?DriverInterface $driver,
        string $userAgent,
        bool $monitoring = false
    ) {
        $this->institution = $institution;
        $this->view = $view;
        $this->driver = $driver;
        $this->userAgent = $userAgent;
        $this->monitoringSystem = $monitoring;
    }

    /**
     * Session start event
     *
     * @param array $params Session data
     *
     * @return void
     */
    public function sessionStart(array $params): void
    {
        if ($this->driver) {
            $this->driver->addNewSession(
                $this->institution,
                $this->view,
                $this->getRequestTypeBitmap(),
                $params
            );
        }
    }

    /**
     * Page view event
     *
     * @param string $controller Controller
     * @param string $action     Action
     *
     * @return void
     */
    public function pageView(string $controller, string $action): void
    {
        if ($this->driver) {
            $this->driver->addPageView(
                $this->institution,
                $this->view,
                $this->getRequestTypeBitmap(),
                $controller,
                $action
            );
        }
    }

    /**
     * Record view event
     *
     * @param AbstractRecord $record Record driver
     *
     * @return void
     */
    public function recordView(AbstractRecord $record): void
    {
        if ($this->driver) {
            if (!($source = $record->tryMethod('getDatasource'))) {
                [$source] = explode('.', $record->getUniqueID(), 2);
            }

            $rawRecord = $record->getRawData();
            $online = 0;
            if (!empty($rawRecord['free_online_boolean'])) {
                $online = 2;
            } elseif (!empty($rawRecord['online_boolean'])) {
                $online = 1;
            }
            $this->driver->addRecordView(
                $this->institution,
                $this->view,
                $this->getRequestTypeBitmap(),
                $record->getSourceIdentifier(),
                $source,
                $record->getUniqueID(),
                $record->tryMethod('getFormats') ?? [],
                $record->tryMethod('getUsageRights') ?? [],
                $online
            );
        }
    }

    /**
     * Get request type as a bitmap
     *
     * @return int
     */
    protected function getRequestTypeBitmap(): int
    {
        $result = 0;
        if ($this->userAgent) {
            $crawlerDetect = new \Jaybizzle\CrawlerDetect\CrawlerDetect();
            if ($crawlerDetect->isCrawler($this->userAgent)) {
                $result |= static::REQUEST_TYPE_CRAWLER;
            }
        }

        if (getenv('VUFIND_API_CALL')) {
            $result |= static::REQUEST_TYPE_API;
        }

        if ($this->monitoringSystem) {
            $result |= static::REQUEST_TYPE_MONITORING;
        }

        return $result;
    }
}
