<?php

/**
 * Availability Status Logic Interface
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2024.
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
interface AvailabilityStatusInterface
{
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
    public function isVisible(): bool;

    /**
     * Set use unknown message
     *
     * @param bool $useUnknownMessage If unknown message shall be used
     *
     * @return void
     */
    public function setUseUnknownMessage(bool $useUnknownMessage): void;

    /**
     * Check if unknown message should be used.
     *
     * @return bool
     */
    public function useUnknownMessage(): bool;

    /**
     * Get status description.
     *
     * @return string
     */
    public function getStatusDescription(): string;

    /**
     * Get status label.
     *
     * @return string
     */
    public function getStatusLabel(): string;

    /**
     * Get schema.
     *
     * @return ?string
     */
    public function getSchema(): ?string;

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
}
