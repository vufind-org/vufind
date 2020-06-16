<?php
/**
 * Row Definition for user_card
 *
 * PHP version 7
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace Finna\Db\Row;

use Laminas\Crypt\BlockCipher;
use Laminas\Crypt\Symmetric\Openssl;

/**
 * Row Definition for user_card
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class UserCard extends \VuFind\Db\Row\UserCard
{
    /**
     * Is encryption enabled?
     *
     * @var bool
     */
    protected $encryptionEnabled = null;

    /**
     * Encryption key used for catalog passwords (null if encryption disabled):
     *
     * @var string
     */
    protected $encryptionKey = null;

    /**
     * VuFind configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $config = null;

    /**
     * Configuration setter
     *
     * @param \Laminas\Config\Config $config VuFind configuration
     *
     * @return void
     */
    public function setConfig(\Laminas\Config\Config $config)
    {
        $this->config = $config;
    }

    /**
     * This is a getter for the Catalog Password. It will return a plaintext version
     * of the password.
     *
     * @return string The Catalog password in plain text
     * @throws \VuFind\Exception\PasswordSecurity
     */
    public function getCatPassword()
    {
        if ($this->passwordEncryptionEnabled()) {
            return isset($this->cat_pass_enc)
                ? $this->encryptOrDecrypt($this->cat_pass_enc, false) : null;
        }
        return isset($this->cat_password) ? $this->cat_password : null;
    }

    /**
     * Is ILS password encryption enabled?
     *
     * @return bool
     */
    protected function passwordEncryptionEnabled()
    {
        if (null === $this->encryptionEnabled) {
            $this->encryptionEnabled
                = isset($this->config->Authentication->encrypt_ils_password)
                ? $this->config->Authentication->encrypt_ils_password : false;
        }
        return $this->encryptionEnabled;
    }

    /**
     * This is a central function for encrypting and decrypting so that
     * logic is all in one location
     *
     * @param string $text    The text to be encrypted or decrypted
     * @param bool   $encrypt True if we wish to encrypt text, False if we wish to
     * decrypt text.
     *
     * @return string|bool    The encrypted/decrypted string
     * @throws \VuFind\Exception\PasswordSecurity
     */
    protected function encryptOrDecrypt($text, $encrypt = true)
    {
        // Ignore empty text:
        if (empty($text)) {
            return $text;
        }

        // Load encryption key from configuration if not already present:
        if (null === $this->encryptionKey) {
            if (!isset($this->config->Authentication->ils_encryption_key)
                || empty($this->config->Authentication->ils_encryption_key)
            ) {
                throw new \VuFind\Exception\PasswordSecurity(
                    'ILS password encryption on, but no key set.'
                );
            }
            $this->encryptionKey = $this->config->Authentication->ils_encryption_key;
        }

        // Perform encryption:
        $algo = isset($this->config->Authentication->ils_encryption_algo)
            ? $this->config->Authentication->ils_encryption_algo
            : 'blowfish';
        $cipher = new BlockCipher(new Openssl(['algorithm' => $algo]));
        $cipher->setKey($this->encryptionKey);
        return $encrypt ? $cipher->encrypt($text) : $cipher->decrypt($text);
    }
}
