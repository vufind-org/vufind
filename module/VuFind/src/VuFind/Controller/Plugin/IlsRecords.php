<?php

/**
 * VuFind Action Helper - ILS Records Support Methods
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2021.
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
 * @package  Controller_Plugins
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Controller\Plugin;

use Laminas\Config\Config;
use VuFind\Record\Loader;

/**
 * Action helper to perform ILS record related actions
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class IlsRecords extends \Laminas\Mvc\Controller\Plugin\AbstractPlugin
{
    use \VuFind\ILS\Logic\SummaryTrait;

    /**
     * VuFind configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * Record loader
     *
     * @var Loader
     */
    protected $loader;

    /**
     * Constructor
     *
     * @param Config $config VuFind configuration
     * @param Loader $loader Record loader
     */
    public function __construct(Config $config, Loader $loader)
    {
        $this->config = $config;
        $this->loader = $loader;
    }

    /**
     * Get record driver objects corresponding to an array of record arrays returned
     * by an ILS driver's methods such as getMyHolds / getMyTransactions.
     *
     * @param array $records Record information
     *
     * @return \VuFind\RecordDriver\AbstractBase[]
     */
    public function getDrivers(array $records): array
    {
        if (!$records) {
            return [];
        }
        $ids = array_map(
            function ($current) {
                return [
                    'id' => $current['id'] ?? '',
                    'source' => $current['source'] ?? DEFAULT_SEARCH_BACKEND,
                ];
            },
            $records
        );
        $drivers = $this->loader->loadBatch($ids, true);
        foreach ($records as $i => $current) {
            // loadBatch takes care of maintaining correct order, so we can access
            // the array by index
            $drivers[$i]->setExtraDetail('ils_details', $current);
        }
        return $drivers;
    }

    /**
     * Collect up to date status information for ajax account notifications.
     *
     * This information is used to trigger a refresh for account notifications if
     * necessary.
     *
     * @param array $records Records for holds, ILL requests or storage retrieval
     * requests
     *
     * @return array
     */
    public function collectRequestStats(array $records): ?array
    {
        // Collect up to date stats for ajax account notifications:
        if (!($this->config->Authentication->enableAjax ?? true)) {
            return null;
        }
        return $this->getRequestSummary(
            array_map(
                function ($record) {
                    return $record->getExtraDetail('ils_details');
                },
                $records
            )
        );
    }
}
