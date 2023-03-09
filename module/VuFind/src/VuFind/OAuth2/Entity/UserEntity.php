<?php
/**
 * OAuth2 user entity implementation.
 *
 * PHP version 7
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

use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\UserEntityInterface;
use OpenIDConnectServer\Entities\ClaimSetInterface;
use VuFind\Db\Row\User;
use VuFind\Db\Table\AccessToken;
use VuFind\ILS\Connection;

/**
 * OAuth2 user entity implementation.
 *
 * @category VuFind
 * @package  OAuth2
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class UserEntity implements UserEntityInterface, ClaimSetInterface
{
    use EntityTrait;

    /**
     * User
     *
     * @var User
     */
    protected $user;

    /**
     * ILS connection
     *
     * @var Connection
     */
    protected $ils;

    /**
     * OAuth2 configuration
     *
     * @var array
     */
    protected $oauth2Config;

    /**
     * Access token table
     *
     * @var AccessToken
     */
    protected $accessTokenTable;

    /**
     * Constructor
     *
     * @param User        $user       User
     * @param Connection  $ils        ILS connection
     * @param array       $config     OAuth2 configuration
     * @param AccessToken $tokenTable AccessToken table
     */
    public function __construct(
        User $user,
        Connection $ils,
        array $config,
        AccessToken $tokenTable
    ) {
        $this->setIdentifier($user->id);
        $this->user = $user;
        $this->ils = $ils;
        $this->oauth2Config = $config;
        $this->accessTokenTable = $tokenTable;
    }

    /**
     * Get claims (attributes) for OpenID Connect
     *
     * @return array
     */
    public function getClaims()
    {
        // Get catalog information if the user has credentials:
        $profile = [];
        $blocked = null;
        if (!empty($this->user->cat_username)) {
            try {
                $patron = $this->ils->patronLogin(
                    $this->user->cat_username,
                    $this->user->getCatPassword()
                );
                $profile = $this->ils->getMyProfile($patron);
                $blocksSupported = $this->ils
                    ->checkCapability('getAccountBlocks', compact('patron'));
                if ($blocksSupported) {
                    $blocks = $this->ils->getAccountBlocks($patron);
                    $blocked = !empty($blocks);
                }
            } catch (\Exception $e) {
                // fall through since we don't know if any of the information is
                // actually required
            }
        }

        $result = [];
        if ($nonce = $this->accessTokenTable->getNonce($this->user->id)) {
            $result['nonce'] = $nonce;
        }

        foreach ($this->oauth2Config['ClaimMappings'] as $claim => $field) {
            switch ($field) {
                case 'age':
                    if ($birthDate = $profile['birthdate'] ?? '') {
                        $date = \DateTime::createFromFormat('Y-m-d', $birthDate);
                        if ($date) {
                            $diff = $date->diff(new \DateTimeImmutable(), true);
                            $result[$claim] = (int)$diff->format('%y');
                        }
                    }
                    break;
                case 'address_json':
                    if ($profile) {
                        // address_json is a specially formatted field for address
                        // information:
                        $street = array_filter(
                            [
                                $profile['address1'] ?? '',
                                $profile['address2'] ?? '',
                            ]
                        );
                        $address = [
                            'street_address' => implode("\n", $street),
                            'locality' => $profile['city'] ?? '',
                            'postal_code' => $profile['zip'] ?? '',
                            'country' => $profile['country'] ?? '',
                        ];
                        $result[$claim] = json_encode($address);
                    }
                    break;
                case 'block_status':
                    // account_blocked is a flag indicating whether the patron has
                    // blocks:
                    $result[$claim] = $blocked;
                    break;
                case 'full_name':
                    // full_name is a special field for firstname + lastname:
                    $result[$claim] = trim(
                        $this->user['firstname'] . ' ' . $this->user['lastname']
                    );
                    break;
                case 'last_language':
                    // Make sure any country code is in uppercase:
                    $value = $this->user->last_language;
                    $parts = explode('-', $value);
                    if (isset($parts[1])) {
                        $value = $parts[0] . '-' . strtoupper($parts[1]);
                    }
                    $result[$claim] = $value;
                    break;
                case 'library_user_id_hash':
                    $id = $profile['cat_id'] ?? $this->user->cat_username ?? null;
                    if ($id) {
                        $result[$claim] = hash(
                            'sha256',
                            $id
                            . $this->oauth2Config['Server']['hashSalt']
                        );
                    }
                default:
                    if (($value = $this->user->{$field} ?? null)
                        || ($value = $profile[$field] ?? null)
                    ) {
                        $result[$claim] = $value;
                    }
                    break;
            }
        }

        return $result;
    }
}
