<?php

/**
 * Online payment view helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\View\Helper\Root;

/**
 * Online payment view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class OnlinePayment extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Renders online payment form.
     *
     * @param string $handler       Payment handler.
     * @param string $transactionId Transaction id.
     * @param ?int   $amount        Total payable amount,
     *                              including transaction fee (in cents), or null if
     *                              user has to choose fees to pay.
     *
     * @return string
     */
    public function __invoke($handler, $transactionId, $amount)
    {
        return $this->getView()->render(
            "Helpers/OnlinePayment/$handler.phtml",
            ['transactionId' => $transactionId, 'amount' => $amount]
        );
    }
}
