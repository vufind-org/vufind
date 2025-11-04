<?php

/**
 * Record image view helper factory
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\View\Helper\Root;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

/**
 * Record image view helper factory
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class RecordImageFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @param ContainerInterface $container     Service Manager
     * @param string             $requestedName Service being created
     * @param null|array         $options       Extra options (optional)
     *
     * @return object
     *
     * @throws \Exception (options not allowed in this implementation)
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ) {
        return new $requestedName(
            $container->get('ViewHelperManager')->get('url')
        );
    }
}
