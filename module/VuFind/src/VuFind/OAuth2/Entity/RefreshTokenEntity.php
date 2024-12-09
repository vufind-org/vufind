<?php

/**
 * OAuth2 refresh token entity implementation.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @package  OAuth2
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\OAuth2\Entity;

use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\RefreshTokenTrait;

/**
 * OAuth2 refresh token entity implementation.
 *
 * @category VuFind
 * @package  OAuth2
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class RefreshTokenEntity implements RefreshTokenEntityInterface, \JsonSerializable
{
    use RefreshTokenTrait;
    use EntityTrait;

    /**
     * Serialize to a JSON string
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        $properties = [
            'identifier',
            'expiryDateTime',
        ];

        $result = [];
        foreach ($properties as $property) {
            $result[$property] = $this->{$property};
        }
        $accessToken = $this->getAccessToken();
        $result['accessToken'] = $accessToken ? $accessToken->getIdentifier() : null;
        return $result;
    }
}
