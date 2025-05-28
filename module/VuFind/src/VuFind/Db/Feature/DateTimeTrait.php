<?php

/**
 * Trait providing date handling support functions.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Feature;

use DateTime;
use DateTimeZone;

/**
 * Trait providing date handling support functions.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
trait DateTimeTrait
{
    /**
     * Get a date or null from non-nullable date with a default value.
     *
     * @param DateTime $date Date
     *
     * @return ?DateTime
     */
    protected function getNullableDateTimeFromNonNullable(DateTime $date): ?DateTime
    {
        // Compare strings to avoid trouble with time zones:
        return $date->format('Y-m-d H:i:s') !== $this->getUnassignedDefaultDateTime()->format('Y-m-d H:i:s')
            ? $date
            : null;
    }

    /**
     * Get a date or default value from nullable date.
     *
     * @param ?DateTime $date Date
     *
     * @return DateTime
     */
    protected function getNonNullableDateTimeFromNullable(?DateTime $date): DateTime
    {
        return $date ?? $this->getUnassignedDefaultDateTime();
    }

    /**
     * Get the value of default DateTime that has not been assigned a real date.
     *
     * @return DateTime
     */
    protected function getUnassignedDefaultDateTime(): DateTime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', '2000-01-01 00:00:00', new DateTimeZone('UTC'));
    }
}
