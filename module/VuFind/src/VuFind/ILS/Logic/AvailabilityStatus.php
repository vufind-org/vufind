<?php

/**
 * Availability Status Logic Class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
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
     * Items availability
     *
     * @var int
     */
    protected int $availability;

    /**
     * If unknown message shall be used
     *
     * @var bool
     */
    protected bool $useUnknownMessage = false;

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
        return (bool)$this->availability;
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
     * Check if status should be visible.
     *
     * @return bool
     */
    public function isVisible(): bool
    {
        return true;
    }

    /**
     * Set use unknown message
     *
     * @param bool $useUnknownMessage If unknown message shall be used
     *
     * @return void
     */
    public function setUseUnknownMessage(bool $useUnknownMessage): void
    {
        $this->useUnknownMessage = $useUnknownMessage;
    }

    /**
     * Check if unknown message should be used.
     *
     * @return bool
     */
    public function useUnknownMessage(): bool
    {
        return $this->useUnknownMessage;
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
            default:
                return 'Uncertain';
        }
    }

    /**
     * Get status label.
     *
     * @return string
     */
    public function getStatusLabel(): string
    {
        switch ($this->availability) {
            case self::STATUS_UNAVAILABLE:
                return 'Checked Out';
            case self::STATUS_AVAILABLE:
                return 'Available';
            default:
                return 'HoldingStatus::availability_uncertain';
        }
    }

    /**
     * Get status class.
     *
     * @return string
     */
    public function getStatusClass(): string
    {
        switch ($this->availability) {
            case self::STATUS_UNAVAILABLE:
                return 'danger';
            case self::STATUS_AVAILABLE:
                return 'success';
            default:
                return 'warning';
        }
    }

    /**
     * Get schema.
     *
     * @return ?string
     */
    public function getSchema(): ?string
    {
        switch ($this->availability) {
            case self::STATUS_UNAVAILABLE:
                return 'http://schema.org/OutOfStock';
            case self::STATUS_AVAILABLE:
                return 'http://schema.org/InStock';
            default:
                return 'http://schema.org/LimitedAvailability';
        }
    }

    /**
     * Get status message key
     *
     * @return ?string
     */
    public function getStatusMessageKey(): ?string
    {
        if ($this->useUnknownMessage) {
            return 'unknown';
        }
        return lcfirst($this->getStatusDescription());
    }

    /**
     * Get status message key.
     *
     * @return string
     */
    public function getStatusMessageTemplate(): string
    {
        if ($this->useUnknownMessage) {
            return 'ajax/status-unknown.phtml';
        }
        return 'ajax/status.phtml';
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
            case AvailabilityStatus::STATUS_UNCERTAIN:
                return 'uncertain';
            default:
                return 'true';
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
            case AvailabilityStatus::STATUS_UNAVAILABLE:
                return 0;
            case AvailabilityStatus::STATUS_UNCERTAIN:
                return 1;
            default:
                return 2;
        }
    }
}
