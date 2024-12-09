<?php

/**
 * Availability Status Logic Interface
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  ILS_Logic
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\ILS\Logic;

/**
 * Availability Status Logic Interface
 *
 * @category VuFind
 * @package  ILS_Logic
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
interface AvailabilityStatusInterface
{
    /**
     * Status code for unavailable items
     *
     * @var int
     */
    public const STATUS_UNAVAILABLE = 0;

    /**
     * Status code for available items
     *
     * @var int
     */
    public const STATUS_AVAILABLE = 1;

    /**
     * Status code for items with uncertain availability
     *
     * @var int
     */
    public const STATUS_UNCERTAIN = 2;

    /**
     * Status code for items where no status information is available
     *
     * @var int
     */
    public const STATUS_UNKNOWN = 3;

    /**
     * Check if available.
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Check if item has given availability status.
     *
     * @param int $availability Availability status
     *
     * @return bool
     */
    public function is(int $availability): bool;

    /**
     * Check if status should be visible.
     *
     * @return bool
     */
    public function isVisibleInHoldings(): bool;

    /**
     * Set visibility status.
     *
     * @param bool $visibilityInHoldingsTab Visibility toggle
     *
     * @return AvailabilityStatus
     */
    public function setVisibilityInHoldings(bool $visibilityInHoldingsTab): AvailabilityStatus;

    /**
     * Get status description.
     *
     * @return string
     */
    public function getStatusDescription(): string;

    /**
     * Get extra status information.
     *
     * @return array
     */
    public function getExtraStatusInformation(): array;

    /**
     * Get status description tokens. Used when status description is being translated.
     *
     * @return array
     */
    public function getStatusDescriptionTokens(): array;

    /**
     * Get schema.org availability URI.
     *
     * @return ?string
     */
    public function getSchemaAvailabilityUri(): ?string;

    /**
     * Convert availability to a string
     *
     * @return string
     */
    public function availabilityAsString(): string;

    /**
     * Compares priority with other availability status for acquiring overall status of multiple status.
     *
     * @param AvailabilityStatusInterface $other Other Availability Status
     *
     * @return int
     */
    public function compareTo(AvailabilityStatusInterface $other): int;

    /**
     * Get status priority.
     *
     * @return int
     */
    public function getPriority(): int;
}
