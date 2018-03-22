<?php
/**
 * VuFind Configuration Provider FlatIni Filter
 *
 * Copyright (C) 2018 Leipzig University Library <info@ub.uni-leipzig.de>
 *
 * PHP version 7
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
 * along with this program; if not, write to the Free Software Foundation,
 * Inc. 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA
 *
 * @category VuFind
 * @package  Config
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU GPLv2
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Config\Provider\Filter;

use VuFind\Config\Factory;
use Zend\EventManager\Filter\FilterIterator as Chain;

/**
 * VuFind Configuration Provider FlatIni Filter
 *
 * @category VuFind
 * @package  Config
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class FlatIni
{
    /**
     * Invokes this filter.
     *
     * @param mixed $context Reference to filter context.
     * @param array $items   List of items to be processed.
     * @param Chain $chain   The remaining filter chain.
     *
     * @return array
     */
    public function __invoke($context, array $items, Chain $chain): array
    {
        $iniReader = Factory::getIniReader();
        $separator = $iniReader->getNestSeparator();
        $iniReader->setNestSeparator(chr(0));
        $result = $chain->next($context, $items, $chain);
        $iniReader->setNestSeparator($separator);
        return $result;
    }
}
