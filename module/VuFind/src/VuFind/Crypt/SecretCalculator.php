<?php

/**
 * Secret calculator
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

use VuFind\Db\Entity\SearchEntityInterface;

/**
 * Secret calculator
 *
 * @category VuFind
 * @package  Crypt
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SecretCalculator
{
    /**
     * Constructor
     *
     * @param HMAC $hmac HMAC generator
     */
    public function __construct(protected HMAC $hmac)
    {
    }

    /**
     * Utility function for generating a token for unsubscribing a saved search.
     *
     * @param SearchEntityInterface $search Object representing saved search
     *
     * @return string token
     */
    public function getSearchUnsubscribeSecret(SearchEntityInterface $search): string
    {
        $user = $search->getUser();
        if (!$user) {
            throw new \Exception("Unexpected missing user in search {$search->getId()}");
        }
        $data = [
            'id' => $search->getId(),
            'user_id' => $user->getId(),
            'created' => $user->getCreated()->format('Y-m-d H:i:s'),
        ];
        return $this->hmac->generate(array_keys($data), $data);
    }
}
