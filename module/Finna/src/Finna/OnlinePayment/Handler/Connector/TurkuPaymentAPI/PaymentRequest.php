<?php

/**
 * Turku Payment API PaymentRequest
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @package  OnlinePayment
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\OnlinePayment\Handler\Connector\TurkuPaymentAPI;

use Paytrail\SDK\Exception\ValidationException;

/**
 * Turku Payment API PaymentRequest
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class PaymentRequest extends \Paytrail\SDK\Request\PaymentRequest
{
    /**
     * Use prices without vat
     *
     * @var bool
     */
    protected $usePricesWithoutVat = true;

    /**
     * Sap organization details
     *
     * @var array
     */
    protected $sapOrganizationDetails = [];

    /**
     * Set use prices without vat
     *
     * @param bool $value Should prices not include vat
     *
     * @return PaymentRequest
     */
    public function setUsePricesWithoutVat(bool $value): PaymentRequest
    {
        $this->usePricesWithoutVat = $value;
        return $this;
    }

    /**
     * Get use prices without vat
     *
     * @return bool
     */
    public function getUsePricesWithoutVat(): bool
    {
        return $this->usePricesWithoutVat;
    }

    /**
     * Set sap organization details
     *
     * @param array $sapOrganizationDetails Sap organization details
     *
     * @return PaymentRequest
     */
    public function setSapOrganizationDetails(
        array $sapOrganizationDetails
    ): PaymentRequest {
        $this->sapOrganizationDetails = $sapOrganizationDetails;
        return $this;
    }

    /**
     * Get sap organization details
     *
     * @return bool
     */
    public function getSapOrganizationDetails(): ?array
    {
        return $this->sapOrganizationDetails;
    }

    /**
     * Validates with Respect\Validation library
     * and throws an exception for invalid objects
     *
     * @throws ValidationException
     *
     * @return bool
     */
    public function validate()
    {
        parent::validate();
        $props = get_object_vars($this);
        if (empty($props['sapOrganizationDetails']['sapSalesOrganization'])) {
            throw new ValidationException(
                'sapOrganizationDetails sapSalesOrganization is empty'
            );
        }
        if (empty($props['sapOrganizationDetails']['sapDistributionChannel'])) {
            throw new ValidationException(
                'sapOrganizationDetails sapDistributionChannel is empty'
            );
        }
        if (empty($props['sapOrganizationDetails']['sapSector'])) {
            throw new ValidationException(
                'sapOrganizationDetails sapSector is empty'
            );
        }
        return true;
    }
}
