<?php

/**
 * Secret calculator
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Crypt
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\Crypt;

use VuFind\Db\Entity\UserEntityInterface;

/**
 * Secret calculator
 *
 * @category VuFind
 * @package  Crypt
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SecretCalculator extends \VuFind\Crypt\SecretCalculator
{
    /**
     * Utility function for generating a token for unsubscribing a saved search.
     *
     * @param UserEntityInterface $user User
     *
     * @return string token
     */
    public function getDueDateReminderUnsubscribeSecret(UserEntityInterface $user): string
    {
        $data = [
            'id' => 'reminder',
            'user_id' => $user->getId(),
            'created' => $user->getCreated()->format('Y-m-d H:i:s'),
        ];
        return $this->hmac->generate(array_keys($data), $data);
    }
}
