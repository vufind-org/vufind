<?php
/**
 * VuFind Configuration Provider ParentYaml Filter
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
 * VuFind Configuration Provider ParentYaml Filter
 *
 * @category VuFind
 * @package  Config
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ParentYaml
{
    /**
     * Invokes this filter.
     *
     * @param mixed $context Reference to filter context.
     * @param array $items   List of items to be filtered.
     * @param Chain $chain   The remaining filter chain.
     *
     * @return array
     */
    public function __invoke($context, array $items, Chain $chain): array
    {
        $result = array_map([$this, 'process'], $items);

        return $chain->isEmpty() ? $result
            : $chain->next($context, $result, $chain);
    }

    /**
     * Processes a single item.
     *
     * @param array $item The item to be processed.
     *
     * @return array
     */
    protected function process(array $item): array
    {
        if ($item['ext'] !== 'yaml') {
            return $item;
        }
        $data = $this->mergeParent($item['data']);

        return array_merge($item, compact('data'));
    }

    /**
     * Recursively merges in parent configuration data declared with
     * <code>@parent_yaml</code> directives.
     *
     * @param array $child The processed configuration data optionally
     *                     containing a <code>@parent_yaml</code> directive.
     *
     * @return array
     */
    protected function mergeParent(array $child)
    {
        if (!isset($child['@parent_yaml'])) {
            return $child;
        }
        $parent = Factory::fromFile($child['@parent_yaml']);
        unset($child['@parent_yaml']);

        return $this->mergeParent(array_replace($parent, $child));
    }
}
