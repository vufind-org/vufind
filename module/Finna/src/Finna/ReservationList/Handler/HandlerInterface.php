<?php

/**
 * Handler interface
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  ReservationList
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */

namespace Finna\ReservationList\Handler;

use Finna\Db\Entity\FinnaResourceListEntityInterface;
use Finna\ReservationList\Form\Form;
use VuFind\Db\Entity\UserEntityInterface;

/**
 * Handler interface
 *
 * @category VuFind
 * @package  ReservationList
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
interface HandlerInterface
{
    /**
     * Places an order
     *
     * @param array               $formValues Values gathered from submitted form
     * @param UserEntityInterface $user       User entity
     *
     * @return array [
     *  external_id: Id in external service or null,
     *  success: true or false,
     *  pickup_date: date for preferred pickup,
     *  connection Type of the connection
     * ]
     */
    public function placeOrder(array $formValues, UserEntityInterface $user): array;

    /**
     * Check list status. Used for external services.
     *
     * @param FinnaResourceListEntityInterface $list List to check for status
     *
     * @return string
     */
    public function getListStatus(FinnaResourceListEntityInterface $list): string;

    /**
     * Get values required for placing the order.
     *
     * @param FinnaResourceListEntityInterface $list          List being ordered
     * @param UserEntityInterface              $user          User who owns the list
     * @param array                            $requestValues Values obtained i.e from post request as array
     *
     * @return array
     */
    public function getValuesForPlaceOrderForm(
        FinnaResourceListEntityInterface $list,
        UserEntityInterface $user,
        array $requestValues
    ): array;

    /**
     * Get form used for placing orders.
     *
     * @param array $prefill Prefill form with these values.
     *
     * @return Form
     */
    public function getPlaceOrderForm(array $prefill = []): Form;

    /**
     * Initialize connection handler
     *
     * @param array $config List specific configuration from ReservationList.yaml
     *
     * @return static
     * @throws \Exception If connection is not configured properly
     */
    public function init(array $config): static;
}
