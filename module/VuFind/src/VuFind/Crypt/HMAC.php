<?php

/**
 * HMAC hash generator
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Crypt
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Crypt;

/**
 * HMAC hash generator wrapper
 *
 * @category VuFind
 * @package  Crypt
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class HMAC
{
    /**
     * Hash key
     *
     * @var string
     */
    protected $hashKey;

    /**
     * Constructor
     *
     * @param string $key Hash key
     */
    public function __construct($key)
    {
        $this->hashKey = $key;
    }

    /**
     * Accepts $keysToHash, a list of array keys, and $keyValueArray, a keyed array
     *
     * @param array $keysToHash    A list of keys to hash
     * @param array $keyValueArray A keyed array
     *
     * @return string A hash_hmac string using md5
     */
    public function generate($keysToHash, $keyValueArray)
    {
        $str = '';
        foreach ($keysToHash as $key) {
            $value = $keyValueArray[$key] ?? '';
            $str .= $key . '=' . $value . '|';
        }
        return hash_hmac('md5', $str, $this->hashKey);
    }
}
