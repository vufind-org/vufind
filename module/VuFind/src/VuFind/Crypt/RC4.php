<?php
/**
 * RC4 encryption class (wrapper around code borrowed from a third-party
 * developer -- see embedded copyright information on encrypt method)
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Crypt
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Crypt;

/**
 * RC4 encryption class (wrapper around code borrowed from a third-party
 * developer -- see embedded copyright information on encrypt method)
 *
 * @category VuFind2
 * @package  Crypt
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class RC4
{
    /**
     * Encrypt given plain text using the key with RC4 algorithm.
     * All parameters and return value are in binary format.
     *
     * @param string $key secret key for encryption
     * @param string $pt  plain text to be encrypted
     *
     * @return string
     */
    public static function encrypt($key, $pt)
    {
        /* RC4 symmetric cipher encryption/decryption
         * Copyright (c) 2006 by Ali Farhadi.
         * released under the terms of the Gnu Public License.
         * see the GPL for details.
         *
         * Email: ali[at]farhadi[dot]ir
         * Website: http://farhadi.ir/
         */
        $s = [];
        for ($i = 0; $i < 256; $i++) {
            $s[$i] = $i;
        }
        $j = 0;
        $x;
        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $s[$i] + ord($key[$i % strlen($key)])) % 256;
            $x = $s[$i];
            $s[$i] = $s[$j];
            $s[$j] = $x;
        }
        $i = 0;
        $j = 0;
        $ct = '';
        $y;
        for ($y = 0; $y < strlen($pt); $y++) {
            $i = ($i + 1) % 256;
            $j = ($j + $s[$i]) % 256;
            $x = $s[$i];
            $s[$i] = $s[$j];
            $s[$j] = $x;
            $ct .= $pt[$y] ^ chr($s[($s[$i] + $s[$j]) % 256]);
        }
        return $ct;
    }

    /**
     * Decrypt given cipher text using the key with RC4 algorithm.
     * All parameters and return value are in binary format.
     *
     * @param string $key secret key for decryption
     * @param string $ct  cipher text to be decrypted
     *
     * @return string
    */
    public static function decrypt($key, $ct)
    {
        return static::encrypt($key, $ct);
    }
}
