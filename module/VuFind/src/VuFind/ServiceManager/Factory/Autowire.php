<?php

/**
 * VuFind Autowiring Attribute
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @package  ServiceManager
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\ServiceManager\Factory;

use Attribute;
use LogicException;

/**
 * VuFind Autowiring Attribute
 *
 * @category VuFind
 * @package  ServiceManager
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[Attribute]
class Autowire
{
    /**
     * Constructor.
     *
     * @param ?string $config     Configuration to inject as an array (mutually exclusive with $service)
     * @param ?string $configType Configuration type (for $config; valid values are 'array' (default), 'object'
     * and 'yaml')
     * @param ?string $service    Service to inject (mutually exclusive with $config)
     * @param ?string $container  Container or plugin manager to use to get the service
     */
    public function __construct(
        public readonly ?string $config = null,
        public readonly ?string $configType = null,
        public readonly ?string $service = null,
        public readonly ?string $container = null,
    ) {
        if (null !== $config) {
            if (null !== $service) {
                throw new LogicException('#[Autowire] attribute cannot contain both config and service.');
            }
            if (null !== $container) {
                throw new LogicException('#[Autowire] attribute cannot contain both config and container.');
            }
        } elseif (null !== $configType) {
            throw new LogicException('#[Autowire] attribute cannot contain configType without config.');
        }
    }
}
