<?php

/**
 * Reservation list status enum.
 *
 * PHP Version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  ReservationList
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\ReservationList\Handler;

/**
 * Reservation list status enum.
 *
 * @category VuFind
 * @package  ReservationList
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
enum ReservationListStatus: string
{
    case UNKNOWN = 'unknown';
    case DELIVERED = 'delivered';
    case IN_PROCESS = 'in_process';
    case CANCELED = 'canceled';
    case ON_LOAN = 'on_loan';
    case RETURNED = 'returned';
    case RENEWED = 'renewed';
    case PENDING = 'pending';

    /**
     * Return a translation key representing the status.
     *
     * @return string
     */
    public function getTranslationKey(): string
    {
        return 'ReservationList::status_' . $this->value;
    }

    /**
     * Return instance of ENUM by mapping a status key into a proper enum status
     *
     * @param string $text Value to map
     *
     * @return string Found mapped value or unknown if not found
     */
    public static function mapEnumFromString(string $text): static
    {
        $statusKeyMappings = [
            'loaned' => 'on_loan',
            'cancelled' => 'canceled',
        ];
        $text = mb_strtolower($text);
        $text = $statusKeyMappings[$text] ?? $text;
        return ReservationListStatus::tryFrom($text) ?? ReservationListStatus::UNKNOWN;
    }
}
