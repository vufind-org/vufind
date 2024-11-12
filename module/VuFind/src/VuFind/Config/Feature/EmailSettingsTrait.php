<?php

/**
 * Trait providing email settings
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @package  Config
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Config\Feature;

use Laminas\Config\Config;

/**
 * Trait providing email settings
 *
 * N.B. User-oriented email settings are handled by \VuFind\Config\AccountCapabilities.
 *
 * @category VuFind
 * @package  Config
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
trait EmailSettingsTrait
{
    /**
     * Get sender email address
     *
     * @param array|Config $config    VuFind configuration
     * @param ?string      $userEmail User's own email address that is used if permitted by settings
     *
     * @return string
     */
    protected function getEmailSenderAddress(array|Config $config, ?string $userEmail = null): string
    {
        if ($config instanceof Config) {
            $config = $config->toArray();
        }
        if ($userEmail && ($config['Mail']['user_email_in_from'] ?? false)) {
            return $userEmail;
        }
        // Check for a setting that's defined and not empty:
        if (!($result = $config['Mail']['default_from'] ?? null)) {
            if (!($result = $config['Site']['email'] ?? null)) {
                throw new \Exception(
                    'Missing configuration for email sender. Please check settings Mail/default_from and Site/email.'
                );
            }
        }
        return $result;
    }
}
