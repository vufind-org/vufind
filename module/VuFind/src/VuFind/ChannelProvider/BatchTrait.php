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
     * Number of results to include in each channel.
     *
     * @var int
     */
    protected $batchSize = 24;

    /**
     * Set the options for the provider.
     *
     * @param array $options Options
     *
     * @return void
     */
	public function setBatchSizeFromOptions(array $options)
	{
        // Calculate batch size
        $itemsPerRow = $options['itemsPerRow'] ?? 6;
        $rowsPerPage = $options['rowsPerPage'] ?? 1;
        $this->batchSize = $itemsPerRow * $rowsPerPage;

        // Set a minimum of 20 to make sure the server isn't hit too often
        while ($this->batchSize <= 20) {
            $this->batchSize *= 2;
        }
	}
}
