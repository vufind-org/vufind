<?php

/**
 * Trait for getting a summary for checkouts, fines, holds, ILL requests or storage
 * retrieval requests.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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
 * @package  ILS
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\ILS\Logic;

use VuFind\Service\CurrencyFormatter;

/**
 * Trait for getting a summary for checkouts, fines, holds, ILL requests or storage
 * retrieval requests.
 *
 * @category VuFind
 * @package  ILS
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
trait SummaryTrait
{
    /**
     * Get a status summary for an array of fines
     *
     * @param iterable          $fines     Fines
     * @param CurrencyFormatter $formatter Currency formatter
     *
     * @return array Associative array with keys total and display.
     */
    protected function getFineSummary(
        iterable $fines,
        CurrencyFormatter $formatter
    ): array {
        $total = 0;
        foreach ($fines as $fine) {
            $total += $fine['balance'];
        }
        $display = $formatter->convertToDisplayFormat($total / 100);
        return compact('total', 'display');
    }

    /**
     * Get a status summary for an array of requests
     *
     * @param iterable $requests Requests
     *
     * @return array Associative array with keys available, in_transit and other.
     */
    protected function getRequestSummary(iterable $requests): array
    {
        $available = 0;
        $in_transit = 0;
        $other = 0;
        foreach ($requests as $request) {
            if ($request['available'] ?? false) {
                ++$available;
            } elseif ($request['in_transit'] ?? false) {
                ++$in_transit;
            } else {
                ++$other;
            }
        }
        return compact('available', 'in_transit', 'other');
    }

    /**
     * Get a status summary for an array of checkouts
     *
     * @param iterable $transactions Checkouts
     *
     * @return array Associative array with keys available, in_transit and other.
     */
    protected function getTransactionSummary(iterable $transactions): array
    {
        $ok = 0;
        $overdue = 0;
        $warn = 0;
        foreach ($transactions as $transaction) {
            switch ($transaction['dueStatus'] ?? '') {
                case 'due':
                    ++$warn;
                    break;
                case 'overdue':
                    ++$overdue;
                    break;
                default:
                    ++$ok;
                    break;
            }
        }
        return compact('ok', 'overdue', 'warn');
    }
}
