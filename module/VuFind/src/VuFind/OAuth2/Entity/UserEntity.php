<?php

/**
 * OAuth2 user entity implementation.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022-2024.
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
use League\OAuth2\Server\Entities\UserEntityInterface as OAuth2UserEntityInterface;
use OpenIDConnectServer\Entities\ClaimSetInterface;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Db\Entity\UserEntityInterface as DbUserEntityInterface;
use VuFind\Db\Service\AccessTokenServiceInterface;
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
class UserEntity implements OAuth2UserEntityInterface, ClaimSetInterface
{
    use EntityTrait;

    /**
     * Constructor
     *
     * @param DbUserEntityInterface       $user               User
     * @param ?Connection                 $ils                ILS connection
     * @param array                       $oauth2Config       OAuth2 configuration
     * @param AccessTokenServiceInterface $accessTokenService Access token service
     * @param ILSAuthenticator            $ilsAuthenticator   ILS authenticator
     */
    public function __construct(
        protected DbUserEntityInterface $user,
        protected ?Connection $ils,
        protected array $oauth2Config,
        protected AccessTokenServiceInterface $accessTokenService,
        protected ILSAuthenticator $ilsAuthenticator
    ) {
        $userIdentifierField = $oauth2Config['Server']['userIdentifierField'] ?? 'id';
        switch ($userIdentifierField) {
            case 'id':
                $userIdentifier = $user->getId();
                break;
            case 'username':
                $userIdentifier = $user->getUsername();
                break;
            case 'cat_id':
                $userIdentifier = $user->getCatId();
                break;
            default:
                $userIdentifier = null;
        }
        if ($userIdentifier === null) {
            throw new \VuFind\Exception\BadConfig(
                "$userIdentifierField empty for user {$user->getId()}."
                . ' The configured user identifier field has to be required.'
            );
        }
        $this->setIdentifier($userIdentifier);
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
        if ($this->ils && !empty($this->user->getCatUsername())) {
            try {
                $patron = $this->ils->patronLogin(
                    $this->user->getCatUsername(),
                    $this->ilsAuthenticator->getCatPasswordForUser($this->user)
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
        if ($nonce = $this->accessTokenService->getNonce($this->user->getId())) {
            $result['nonce'] = $nonce;
        }

        foreach ($this->oauth2Config['ClaimMappings'] as $claim => $field) {
            // Map legacy table field names to entity interface methods
            $field = match ($field) {
                'id' => 'getId',
                'firstname' => 'getFirstname',
                'lastname' => 'getLastname',
                'email' => 'getEmail',
                'last_language' => 'getLastLanguage',
                default => $field
            };

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
                    // block_status is a flag indicating whether the patron has
                    // blocks:
                    $result[$claim] = $blocked;
                    break;
                case 'full_name':
                    // full_name is a special field for firstname + lastname:
                    $result[$claim] = trim(
                        $this->user->getFirstname() . ' ' . $this->user->getLastname()
                    );
                    break;
                case 'getLastLanguage':
                    // Make sure any country code is in uppercase:
                    $value = $this->user->getLastLanguage();
                    $parts = explode('-', $value);
                    if (isset($parts[1])) {
                        $value = $parts[0] . '-' . strtoupper($parts[1]);
                    }
                    $result[$claim] = $value;
                    break;
                case 'library_user_id_hash':
                    $id = $profile['cat_id'] ?? $this->user->getCatUsername() ?? null;
                    if ($id) {
                        $result[$claim] = hash(
                            'sha256',
                            $id . $this->oauth2Config['Server']['hashSalt']
                        );
                    }
                    break;
                default:
                    if (
                        (method_exists($this->user, $field)
                        && $value = $this->user->{$field}())
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
