<?php

/**
 * Base62 generator
 *
 * Class to encode and decode numbers using base62
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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
 * @package  VuFind\Crypt
 * @author   Cornelius Amzar <cornelius.amzar@bsz-bw.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Crypt;

use Exception;

use function intval;
use function strlen;

/**
 * Base62 generator
 *
 * Class to encode and decode numbers using base62
 *
 * @category VuFind
 * @package  Crypt
 * @author   Cornelius Amzar <cornelius.amzar@bsz-bw.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Base62
{
    public const BASE62_ALPHABET
        = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    public const BASE62_BASE = 62;

    /**
     * Common base62 encoding function.
     * Implemented here so we don't need additional PHP modules like bcmath.
     *
     * @param string $base10Number Number to encode
     *
     * @return string
     *
     * @throws Exception
     */
    public function encode($base10Number)
    {
        $binaryNumber = intval($base10Number);
        if ($binaryNumber === 0) {
            throw new Exception('not a base10 number: "' . $base10Number . '"');
        }

        $base62Number = '';
        while ($binaryNumber != 0) {
            $base62Number = self::BASE62_ALPHABET[$binaryNumber % self::BASE62_BASE]
                . $base62Number;
            $binaryNumber = intdiv($binaryNumber, self::BASE62_BASE);
        }

        return ($base62Number == '') ? '0' : $base62Number;
    }

    /**
     * Common base62 decoding function.
     * Implemented here so we don't need additional PHP modules like bcmath.
     *
     * @param string $base62Number Number to decode
     *
     * @return int
     *
     * @throws Exception
     */
    public function decode($base62Number)
    {
        $binaryNumber = 0;
        for ($i = 0; $i < strlen($base62Number); ++$i) {
            $digit = $base62Number[$i];
            $strpos = strpos(self::BASE62_ALPHABET, $digit);
            if ($strpos === false) {
                throw new Exception('not a base62 digit: "' . $digit . '"');
            }

            $binaryNumber *= self::BASE62_BASE;
            $binaryNumber += $strpos;
        }
        return $binaryNumber;
    }
}
