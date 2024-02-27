<?php

/**
 * OAuth2 scope entity implementation.
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

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\ScopeTrait;

/**
 * OAuth2 scope entity implementation.
 *
 * @category VuFind
 * @package  OAuth2
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ScopeEntity implements ScopeEntityInterface
{
    use EntityTrait;
    use ScopeTrait;

    /**
     * Scope description
     *
     * @var string
     */
    protected $description;

    /**
     * Whether the scope is hidden from the scope list
     *
     * @var bool
     */
    protected $hidden;

    /**
     * Whether the scope requires data from an ILS account
     *
     * @var bool
     */
    protected $ilsNeeded;

    /**
     * Constructor
     *
     * @param array $config Scope configuration
     */
    public function __construct(array $config)
    {
        foreach (['identifier', 'description'] as $required) {
            if (!isset($config[$required])) {
                throw new \Exception("OAuth2 scope config missing '$required'");
            }
        }
        $this->setIdentifier($config['identifier']);
        $this->setDescription($config['description']);
        $this->setILSNeeded((bool)($config['ils'] ?? false));
        $this->setHidden($config['hidden'] ?? false);
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Set description
     *
     * @param string $description Description
     *
     * @return void
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * Get hidden flag
     *
     * @return bool
     */
    public function getHidden(): bool
    {
        return $this->hidden;
    }

    /**
     * Set hidden flag
     *
     * @param bool $value New value
     *
     * @return void
     */
    public function setHidden(bool $value): void
    {
        $this->hidden = $value;
    }

    /**
     * Get ILS needed flag
     *
     * @return bool
     */
    public function getILSNeeded(): bool
    {
        return $this->ilsNeeded;
    }

    /**
     * Set ILS needed flag
     *
     * @param bool $value New value
     *
     * @return void
     */
    public function setILSNeeded(bool $value): void
    {
        $this->ilsNeeded = $value;
    }
}
