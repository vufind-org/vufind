<?php

/**
 * OAuth2 authorization code entity implementation.
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

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\Traits\AuthCodeTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;

/**
 * OAuth2 authorization code entity implementation.
 *
 * @category VuFind
 * @package  OAuth2
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class AuthCodeEntity implements AuthCodeEntityInterface, \JsonSerializable
{
    use AuthCodeTrait;
    use TokenEntityTrait;
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
            'userIdentifier',
            'redirectUri',
            'scopes',
        ];

        $result = [];
        foreach ($properties as $property) {
            $result[$property] = $this->{$property};
        }
        $client = $this->getClient();
        $result['clientIdentifier'] = $client ? $client->getIdentifier() : null;

        return $result;
    }
}
