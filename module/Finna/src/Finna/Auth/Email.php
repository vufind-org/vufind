<?php

/**
 * Email authentication module.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @package  Authentication
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */

namespace Finna\Auth;

/**
 * Email authentication module.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */
class Email extends \VuFind\Auth\Email
{
    /**
     * Update the database using login user details, then return the User object.
     *
     * @param array $info User details returned by the login initiator like ILS.
     *
     * @throws AuthException
     * @return UserEntityInterface Processed User object.
     */
    protected function processUser($info)
    {
        // Add institution prefix to id
        if (isset($info['id'])) {
            $config = $this->getConfig()->toArray();
            if ($institution = $config['Site']['institution'] ?? null) {
                $info['id'] = "$institution:" . $info['id'];
            }
        }
        return parent::processUser($info);
    }
}
