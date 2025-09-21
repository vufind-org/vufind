<?php

/**
 * Trait for handling online payment in ILS drivers.
 *
 * Prerequisites:
 *
 * - Driver configuration as an array in $this->config
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
 * @package  ILS
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\ILS\Logic;

use function in_array;

/**
 * Trait for handling online payment in ILS drivers.
 *
 * @category VuFind
 * @package  ILS
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
trait OnlinePaymentTrait
{
    /**
     * Return details on fees payable online.
     *
     * @param array  $patron          Patron
     * @param array  $fines           Patron's fines
     * @param ?array $selectedFineIds Selected fines
     *
     * @throws ILSException
     * @return array Associative array of payment details
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getOnlinePaymentDetails(array $patron, array $fines, ?array $selectedFineIds): array
    {
        $amount = 0;
        $payableFines = [];
        foreach ($fines as $fine) {
            // Nothing can be paid if there are blocking fines:
            if ($this->fineBlocksPayment($fine)) {
                return [
                    'payable' => false,
                    'amount' => 0,
                    'fines' => [],
                    'reason' => 'Payment::fines_contain_nonpayable_fees',
                ];
            }
            if (
                null !== $selectedFineIds
                && !in_array($fine['fineId'] ?? '', $selectedFineIds)
            ) {
                continue;
            }
            if ($fine['payableOnline'] ?? false) {
                $amount += $fine['balance'];
                $payableFines[] = $fine;
            }
        }
        return [
            'payable' => $amount > 0,
            'amount' => $amount,
            'fines' => $payableFines,
        ];
    }

    /**
     * Check if a fine is payable.
     *
     * @param array $fine Fine
     *
     * @return bool
     */
    protected function fineIsPayable(array $fine): bool
    {
        if ($fine['balance'] <= 0) {
            return false;
        }
        $paymentConfig = $this->config['OnlinePayment'] ?? [];
        if (in_array($fine['fine'] ?? '', $paymentConfig['nonPayableTypes'] ?? [])) {
            return false;
        }
        foreach ((array)($paymentConfig['nonPayableDescriptions'] ?? []) as $pattern) {
            if (preg_match($pattern, $fine['description'] ?? '')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a fine should completely block payment.
     *
     * @param array $fine Fine
     *
     * @return bool
     */
    protected function fineBlocksPayment(array $fine): bool
    {
        $paymentConfig = $this->config['OnlinePayment'] ?? [];
        if (in_array($fine['fine'] ?? '', $paymentConfig['blockingNonPayableTypes'] ?? [])) {
            return true;
        }
        foreach ((array)($paymentConfig['blockingNonPayableDescriptions'] ?? []) as $pattern) {
            if (str_starts_with($pattern, '/') && preg_match('{/\w?$}', $pattern)) {
                if (preg_match($pattern, $fine['description'] ?? '')) {
                    return true;
                }
            } else {
                if ($pattern === $fine['description'] ?? '') {
                    return true;
                }
            }
        }
        return false;
    }
}
