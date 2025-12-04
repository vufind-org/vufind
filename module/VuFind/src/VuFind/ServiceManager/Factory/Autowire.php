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
class Autowire
{
    /**
     * Constructor.
     *
     * Use only one of the following parameters.
     *
     * @param string $config  Configuration to inject (as an array)
     * @param string $service Service to inject
     */
    public function __construct(
        public readonly ?string $config = null,
        public readonly ?string $service = null,
    ) {
        if (null !== $config && null !== $service) {
            throw new LogicException('#[Autowire] attribute cannot contain both config and service.');
        }
    }
}
