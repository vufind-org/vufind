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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
     * Is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * Get translation for title
     *
     * @param string $language Language to get title for
     *
     * @return string
     */
    public function getTitle(string $language): string;

    /**
     * Get translation for description
     *
     * @param string $language Language to get description for
     *
     * @return string
     */
    public function getDescription(string $language): string;

    /**
     * Get address information
     *
     * @return array
     */
    public function getAddress(): array;

    /**
     * Get recipient
     *
     * @return array
     */
    public function getRecipient(): array;

    /**
     * Check if library card matches to allowed sources
     *
     * @param string $libraryCardSource Library card source
     *
     * @return bool
     */
    public function cardIsValid(string $libraryCardSource): bool;

    /**
     * Check if datasource matches to allowed sources
     *
     * @param string $datasource Datasource
     *
     * @return bool
     */
    public function datasourceIsValid(string $datasource): bool;

    /**
     * Get connection type
     *
     * @return string
     */
    public function getConnectionType(): string;

    /**
     * Get connection settings
     *
     * @return array
     */
    public function getConnectionSettings(): array;

    /**
     * Get institution
     *
     * @return string
     */
    public function getInstitution(): string;

    /**
     * Get identifier
     *
     * @return string
     */
    public function getIdentifier(): string;

    /**
     * Get all list properties
     *
     * @return array
     */
    public function getAsArray(): array;

    /**
     * Get api url
     *
     * @return string
     */
    public function getApiUrl(): string;

    /**
     * Get api secret
     *
     * @return string
     */
    public function getApiSecret(): string;

    /**
     * Get email sender name
     *
     * @return string
     */
    public function getSenderName(): string;

    /**
     * Get email sender
     *
     * @return string
     */
    public function getSenderEmail(): string;

    /**
     * Get email sender
     *
     * @return string
     */
    public function getEmailSubject(): string;

    /**
     * Use patron id to send information
     *
     * @return bool
     */
    public function getUsePatronId(): bool;

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
    public function getValuesForListOrder(
        FinnaResourceListEntityInterface $list,
        UserEntityInterface $user,
        array $requestValues
    ): array;

    /**
     * Get values required for placing single record order.
     *
     * @param FinnaResourceListEntityInterface $list          List being ordered
     * @param UserEntityInterface              $user          User who owns the list
     * @param array                            $requestValues Values obtained i.e from post request as array
     *
     * @return array
     */
    public function getValuesForSingleOrder(
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
     * Get form used for placing singular orders.
     *
     * @param array $prefill Prefill form with these values.
     *
     * @return Form
     */
    public function getSingleOrderForm(array $prefill = []): Form;

    /**
     * Initialize connection handler
     *
     * @param string $institution List owner institution code
     * @param array  $config      List specific configuration as an array
     *
     * @return static
     */
    public function init(string $institution, array $config = []): static;
}
