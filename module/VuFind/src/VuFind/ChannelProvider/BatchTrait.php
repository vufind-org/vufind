<?php

/**
 * Trait for ChannelProviders to configure and calculate batch sizes
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
 * @package  ServiceManager
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\ChannelProvider;

/**
 * Trait for ChannelProviders to configure and calculate batch sizes
 *
 * @category VuFind
 * @package  ServiceManager
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
trait BatchTrait
{
    /**
     * Number of results to fetch from providers.
     * Calculated from itemsPerRow and rowsPerPage, min 20.
     *
     * @var int
     */
    protected $batchSize = 24;

    /**
     * Number of results to fetch from providers.
     * Calculated from itemsPerRow and rowsPerPage, min 20.
     *
     * @var int
     */
    protected $maxBatchSize = 48;

    /**
     * Calculate and set the provider's batch-related properties from the provided options array.
     *
     * @param array $options Options
     *
     * @return void
     */
    public function setBatchSizeFromOptions(array $options): void
    {
        // Calculate batch size
        $itemsPerRow = $options['itemsPerRow'] ?? 6;
        $rowsPerPage = $options['rowsPerPage'] ?? 1;
        $this->maxBatchSize = $options['maxBatchSize'] ?? 48;
        $this->batchSize = min(
            self::calcBatchSize($itemsPerRow, $rowsPerPage),
            $this->maxBatchSize,
        );
    }

    /**
     * Calculate batch size (using row size, page size, and minimum of 20).
     *
     * @param int $itemsPerRow Items per row
     * @param int $rowsPerPage Rows per page
     *
     * @return int
     */
    public static function calcBatchSize(int $itemsPerRow, int $rowsPerPage): int
    {
        $batchSize = $itemsPerRow * $rowsPerPage;

        // Set a minimum of 20 so that smaller page sizes do not result in excessive server requests
        while ($batchSize < 20) {
            $batchSize *= 2;
        }

        return $batchSize;
    }
}
