<?php

/**
 * Database service for user.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @package  Database
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use Doctrine\ORM\EntityManager;
use Laminas\Crypt\BlockCipher as BlockCipher;
use Laminas\Crypt\Symmetric\Openssl;
use VuFind\Db\Entity\PluginManager as EntityPluginManager;
use VuFind\Db\Entity\User;

/**
 * Database service for user.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class UserService extends AbstractService
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
     * Constructor
     *
     * @param EntityManager       $entityManager       Doctrine ORM entity manager
     * @param EntityPluginManager $entityPluginManager VuFind entity plugin manager
     */
    public function __construct(
        EntityManager $entityManager,
        EntityPluginManager $entityPluginManager
    ) {
        parent::__construct($entityManager, $entityPluginManager);
    }

    /**
     * Lookup and return a user.
     *
     * @param int $id id value
     *
     * @return User
     */
    public function getUserById($id)
    {
        $user = $this->entityManager->find(
            $this->getEntityClass(\VuFind\Db\Entity\User::class),
            $id
        );
        return $user;
    }

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
     * Is ILS password encryption enabled?
     *
     * @return bool
     */
    public function passwordEncryptionEnabled()
    {
        if (null === $this->encryptionEnabled) {
            $this->encryptionEnabled
                = $this->config->Authentication->encrypt_ils_password ?? false;
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
    public function encryptOrDecrypt($text, $encrypt = true)
    {
        // Ignore empty text:
        if (empty($text)) {
            return $text;
        }

        $configAuth = $this->config->Authentication ?? new \Laminas\Config\Config([]);

        // Load encryption key from configuration if not already present:
        if ($this->encryptionKey === null) {
            if (empty($configAuth->ils_encryption_key)) {
                throw new \VuFind\Exception\PasswordSecurity(
                    'ILS password encryption on, but no key set.'
                );
            }

            $this->encryptionKey = $configAuth->ils_encryption_key;
        }

        // Perform encryption:
        $algo = $configAuth->ils_encryption_algo ?? 'blowfish';

        // Check if OpenSSL error is caused by blowfish support
        try {
            $cipher = new BlockCipher(new Openssl(['algorithm' => $algo]));
            if ($algo == 'blowfish') {
                trigger_error(
                    'Deprecated encryption algorithm (blowfish) detected',
                    E_USER_DEPRECATED
                );
            }
        } catch (\InvalidArgumentException $e) {
            if ($algo == 'blowfish') {
                throw new \VuFind\Exception\PasswordSecurity(
                    'The blowfish encryption algorithm ' .
                    'is not supported by your version of OpenSSL. ' .
                    'Please visit /Upgrade/CriticalFixBlowfish for further details.'
                );
            } else {
                throw $e;
            }
        }
        $cipher->setKey($this->encryptionKey);
        return $encrypt ? $cipher->encrypt($text) : $cipher->decrypt($text);
    }
}
