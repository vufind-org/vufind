<?php
/**
 * RC4 encryption class (wrapper around third-party functions)
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

require_once APPLICATION_PATH . '/vendor/3rdparty/rc4.php';

/**
 * RC4 encryption class (wrapper around third-party functions)
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
        return \rc4Encrypt($key, $pt);
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
        return \rc4Decrypt($key, $ct);
    }
}
