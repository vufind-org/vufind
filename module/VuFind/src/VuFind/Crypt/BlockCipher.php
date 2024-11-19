<?php

/**
 * Block cipher class
 *
 * This class was developed to replace the deprecated \Laminas\Crypt\BlockCipher
 * class. Its default behavior is inspired by that earlier class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Crypt;

use Laminas\Crypt\BlockCipher as LaminasCipher;
use Laminas\Crypt\Symmetric\Openssl;

/**
 * Block cipher class
 *
 * @category VuFind
 * @package  Crypt
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class BlockCipher
{
    /**
     * Cipher object.
     *
     * @var LaminasCipher
     */
    protected $cipher;

    /**
     * Constructor
     *
     * @param array $options Options (supported key: algorithm)
     */
    public function __construct(array $options = [])
    {
        $this->cipher = new LaminasCipher(new Openssl($options));
    }

    /**
     * Set the encryption key
     *
     * @param string $key Encryption key
     *
     * @return void
     */
    public function setKey(string $key): void
    {
        $this->cipher->setKey($key);
    }

    /**
     * Decrypt some data; return decrypted data (or false on error).
     *
     * @param mixed $data Data to decrypt
     *
     * @return string|bool
     */
    public function decrypt($data): string|bool
    {
        return $this->cipher->decrypt($data);
    }

    /**
     * Encrypt some data; return encrypted data (or false on error).
     *
     * @param mixed $data Data to encrypt
     *
     * @return string|bool
     */
    public function encrypt($data)
    {
        return $this->cipher->encrypt($data);
    }
}
