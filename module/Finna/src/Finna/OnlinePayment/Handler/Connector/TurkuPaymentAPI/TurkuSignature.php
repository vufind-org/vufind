<?php

/**
 * Turku Payment API Signature
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

use Paytrail\SDK\Exception\HmacException;

use function is_array;

/**
 * Turku Payment API Signature
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class TurkuSignature
{
    /**
     * Calculate TurkuPayment hash
     *
     * @param array[] $params       HTTP headers in an associative array.
     * @param string  $body         HTTP request body,
     *                              empty string for GET requests
     * @param string  $secretKey    The merchant secret key.
     * @param string  $timeStamp    Timestamp from params
     * @param string  $platformName Name of the application
     *
     * @return string SHA-256 hash
     */
    public static function calculcateHash(
        array $params = [],
        string $body = '',
        string $secretKey = '',
        string $timeStamp = '',
        string $platformName = ''
    ) {
        // Keep only checkout- params, more relevant for response validation.
        // PlatformName and timeStamp will be prepended to the resulting string below
        $includedKeys = array_filter(
            array_keys($params),
            function ($key) {
                return preg_match('/^checkout-/', $key);
            }
        );

        // Keys must be sorted alphabetically
        sort($includedKeys, SORT_STRING);

        $hashPayload = array_map(
            function ($key) use ($params) {
                // Responses have headers in an array.
                $param = is_array($params[$key]) ? $params[$key][0] : $params[$key];

                return implode(':', [$key, $param]);
            },
            $includedKeys
        );
        array_push($hashPayload, $body);
        return hash(
            'sha256',
            $platformName .
            $timeStamp .
            ($body ?: implode("\n", $hashPayload)) .
            $secretKey
        );
    }

    /**
     * Evaluate a response authorization validity.
     *
     * @param array  $params       The response parameters.
     * @param string $body         The response body.
     * @param string $signature    The response signature key.
     * @param string $secretKey    The merchant secret key.
     * @param string $timeStamp    Timestamp from params
     * @param string $platformName Name of the application
     *
     * @throws HmacException
     *
     * @return void
     */
    public static function validateHash(
        array $params = [],
        string $body = '',
        string $signature = '',
        string $secretKey = '',
        string $timeStamp = '',
        string $platformName = ''
    ) {
        $hash = static::calculcateHash(
            $params,
            $body,
            $secretKey,
            $timeStamp,
            $platformName
        );

        if ($hash !== $signature) {
            throw new HmacException('Hash authorization is invalid.', 401);
        }
    }
}
