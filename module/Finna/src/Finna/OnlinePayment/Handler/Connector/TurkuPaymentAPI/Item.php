<?php

/**
 * Turku Payment API Item
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
 * Turku Payment API Item
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Item extends \Paytrail\SDK\Model\Item
{
    /**
     * Holds required sap products
     *
     * @var array
     */
    protected $sapProduct = [];

    /**
     * Set sapProduct
     *
     * @param array $sapProduct The sapProduct
     *
     * @return Item
     */
    public function setSapProduct(array $sapProduct): Item
    {
        $this->sapProduct = $sapProduct;
        return $this;
    }

    /**
     * Get sapProduct
     *
     * @return array
     */
    public function getSapProduct(): ?array
    {
        return $this->sapProduct;
    }

    /**
     * Validates with Respect\Validation library and
     * throws an exception for invalid objects
     *
     * @throws ValidationException
     *
     * @return bool
     */
    public function validate()
    {
        parent::validate();
        $props = get_object_vars($this);
        if (empty($props['sapProduct']['sapCode'])) {
            throw new ValidationException('sapProduct sapCode is empty');
        }
        if (empty($props['sapProduct']['sapOfficeCode'])) {
            throw new ValidationException('sapProduct sapOfficeCode is empty');
        }
        return true;
    }
}
