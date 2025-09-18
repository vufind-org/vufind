<?php

/**
 * Database service interface for PaymentFee.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024-2025.
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

declare(strict_types=1);

namespace VuFind\Db\Service;

use VuFind\Db\Entity\PaymentEntityInterface;
use VuFind\Db\Entity\PaymentFeeEntityInterface;

/**
 * Database service interface for PaymentFee.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface PaymentFeeServiceInterface extends DbServiceInterface
{
    /**
     * Create a PaymentFee entity object.
     *
     * @return PaymentFeeEntityInterface
     */
    public function createEntity(): PaymentFeeEntityInterface;

    /**
     * Get fines associated with a payment.
     *
     * @param PaymentEntityInterface $payment Payment
     *
     * @return PaymentFeeEntityInterface[]
     */
    public function getFeesForPayment(PaymentEntityInterface $payment): array;

    /**
     * Get IDs from fines associated with a payment
     *
     * @param PaymentEntityInterface $payment Payment
     *
     * @return string[]
     */
    public function getFineIdsForPayment(PaymentEntityInterface $payment): array;
}
