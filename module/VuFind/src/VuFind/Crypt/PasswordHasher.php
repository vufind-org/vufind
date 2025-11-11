<?php

/**
 * Password hasher
 *
 * This class was developed to replace the deprecated \Laminas\Crypt\Password\Bcrypt
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Crypt
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Crypt;

use function password_hash;
use function password_verify;

use const PASSWORD_BCRYPT;

/**
 * Password hasher
 *
 * @category VuFind
 * @package  Crypt
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class PasswordHasher
{
    /**
     * Algorithm to use for hashing
     *
     * @var string
     */
    protected string $algorithm = PASSWORD_BCRYPT;

    /**
     * Cost of hashing
     *
     * @var int
     */
    protected int $cost = 10;

    /**
     * Create a hash from a password
     *
     * @param string $password Password to hash
     *
     * @return string
     */
    public function create(string $password): string
    {
        $options = ['cost' => $this->cost];
        return password_hash($password, $this->algorithm, $options);
    }

    /**
     * Does the provided password match the provided hash value?
     *
     * @param string $password Password to check
     * @param string $hash     Hash to compare against
     *
     * @return bool
     */
    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
