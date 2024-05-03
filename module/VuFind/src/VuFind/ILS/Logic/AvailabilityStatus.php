<?php

/**
 * Availability Status Logic Class
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
 * Availability Status Logic Class
 *
 * @category VuFind
 * @package  ILS_Logic
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class AvailabilityStatus implements AvailabilityStatusInterface
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
     * Items availability
     *
     * @var int
     */
    protected int $availability;

    /**
     * Constructor
     *
     * @param int|bool $availability Availability
     * @param string   $status       Status Description
     */
    public function __construct(int|bool $availability, protected string $status = '')
    {
        $this->availability = (int)$availability;
    }

    /**
     * Check if available.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->availability === 1 || $this->availability === 2;
    }

    /**
     * Check if item has given availability status.
     *
     * @param int $availability Availability status
     *
     * @return bool
     */
    public function is(int $availability): bool
    {
        return $this->availability === $availability;
    }

    /**
     * Check if status should be visible in the holdings tab.
     *
     * @return bool
     */
    public function isVisible(): bool
    {
        // Can be overridden if the status should not be visible in the holdings tab,
        return true;
    }

    /**
     * Get status description.
     *
     * @return string
     */
    public function getStatusDescription(): string
    {
        if (!empty($this->status)) {
            return $this->status;
        }
        switch ($this->availability) {
            case self::STATUS_UNAVAILABLE:
                return 'Unavailable';
            case self::STATUS_AVAILABLE:
                return 'Available';
            case self::STATUS_UNKNOWN:
                return 'status_unknown_message';
            default:
                return 'Uncertain';
        }
    }

    /**
     * Get schema.org availability URI.
     *
     * @return ?string
     */
    public function getSchemaAvailabilityUri(): ?string
    {
        switch ($this->availability) {
            case self::STATUS_UNAVAILABLE:
                return 'http://schema.org/OutOfStock';
            case self::STATUS_AVAILABLE:
                return 'http://schema.org/InStock';
            case self::STATUS_UNKNOWN:
                return null;
            default:
                return 'http://schema.org/LimitedAvailability';
        }
    }

    /**
     * Convert availability to a string
     *
     * @return string
     */
    public function availabilityAsString(): string
    {
        switch ($this->availability) {
            case AvailabilityStatus::STATUS_UNAVAILABLE:
                return 'false';
            case AvailabilityStatus::STATUS_AVAILABLE:
                return 'true';
            case AvailabilityStatus::STATUS_UNKNOWN:
                return 'unknown';
            default:
                return 'uncertain';
        }
    }

    /**
     * Compares priority with other availability status for acquiring overall status of multiple status.
     *
     * @param AvailabilityStatusInterface $other Other Availability Status
     *
     * @return int
     */
    public function compareTo(AvailabilityStatusInterface $other): int
    {
        return $other->getPriority() <=> $this->getPriority();
    }

    /**
     * Get status priority.
     *
     * @return int
     */
    protected function getPriority(): int
    {
        switch ($this->availability) {
            case AvailabilityStatus::STATUS_UNKNOWN:
                return 0;
            case AvailabilityStatus::STATUS_UNAVAILABLE:
                return 1;
            case AvailabilityStatus::STATUS_UNCERTAIN:
                return 2;
            default:
                return 3;
        }
    }
}
