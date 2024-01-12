<?php

/**
 * OAuth2 client entity implementation.
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

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use VuFind\Exception\BadConfig as BadConfigException;

/**
 * OAuth2 client entity implementation.
 *
 * @category VuFind
 * @package  OAuth2
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ClientEntity implements ClientEntityInterface
{
    use EntityTrait;
    use ClientTrait;

    /**
     * Constructor
     *
     * @param array $config Client configuration
     */
    public function __construct(array $config)
    {
        foreach (['identifier', 'name', 'redirectUri'] as $required) {
            if (!isset($config[$required])) {
                throw new BadConfigException(
                    "OAuth2 client config missing '$required'"
                );
            }
        }
        $this->setIdentifier($config['identifier']);
        $this->setName($config['name']);
        $this->redirectUri = $config['redirectUri'];
        $this->isConfidential = (bool)($config['isConfidential'] ?? false);
        if (!$this->isConfidential && !empty($config['secret'])) {
            throw new BadConfigException(
                'OAuth2 client config must not specify a secret for a'
                . ' non-confidential client'
            );
        }
    }

    /**
     * Set the client's name.
     *
     * @param string $name Name
     *
     * @return void
     */
    public function setName($name)
    {
        $this->name = $name;
    }
}
