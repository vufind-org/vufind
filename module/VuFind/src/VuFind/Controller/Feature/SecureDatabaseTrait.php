<?php

/**
 * VuFind Action Feature Trait - Secure database detection trait
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
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Controller\Feature;

use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Db\Service\UserServiceInterface;

use function count;

/**
 * VuFind Action Feature Trait - Configuration file path methods
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait SecureDatabaseTrait
{
    /**
     * Get an array containing an ILS encryption algorithm and a randomly generated
     * key.
     *
     * @return array
     */
    protected function getSecureAlgorithmAndKey()
    {
        // Make example hash for AES
        $alpha = 'abcdefghijklmnopqrstuvwxyz';
        $chars = str_repeat($alpha . strtoupper($alpha) . '0123456789,.@#%^&*', 4);
        return ['aes', substr(str_shuffle($chars), 0, 32)];
    }

    /**
     * Does the instance have secure database configuration and contents?
     *
     * @return bool
     */
    protected function hasSecureDatabase(): bool
    {
        // Are configuration settings missing?
        $config = $this->getConfig();
        $status = ($config->Authentication->hash_passwords ?? false)
            && ($config->Authentication->encrypt_ils_password ?? false);

        // If we're correctly configured, check that the data in the database is ok:
        if ($status) {
            try {
                $userRows = $this->getDbService(UserServiceInterface::class)->getInsecureRows();
                $cardRows = $this->getDbService(UserCardServiceInterface::class)->getInsecureRows();
                $status = (count($userRows) + count($cardRows) == 0);
            } catch (\Exception $e) {
                // Any exception means we have a problem!
                $status = false;
            }
        }

        return $status;
    }
}
