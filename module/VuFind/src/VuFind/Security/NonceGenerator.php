<?php

/**
 * Class NonceGenerator
 *
 * PHP version 8
 *
 * Copyright (C) Moravian Library 2019.
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
 * @package  Security
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/ Wiki
 */

namespace VuFind\Security;

/**
 * VuFind class for generating nonce (number used once) used by content security
 * policy.
 *
 * @category VuFind
 * @package  Security
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/ Wiki
 *
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 */
class NonceGenerator
{
    /**
     * Generated nonce (number used once)
     *
     * @var string
     */
    protected string $nonce = '';

    /**
     * Generates a random nonce parameter.
     *
     * @return string
     * @throws \Exception
     */
    public function getNonce(): string
    {
        if (!$this->nonce) {
            $this->nonce = base64_encode(random_bytes(32));
        }
        return $this->nonce;
    }
}
