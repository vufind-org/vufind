<?php

/**
 * Timed method blocks view helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025
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
 * @package  View_Helpers
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

/**
 * Timed method blocks view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class MethodTimedBlocks extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Get TimedBlocks settings from driver configuration
     *
     * @param string $methodName Method to check
     *
     * @return array
     */
    public function __invoke(string $methodName): array
    {
        $ils = $this->getView()->plugin('ils');
        $functionConfig = $ils()->checkCapability('getConfig', ['TimedBlocks'])
            ? $ils()->getDriver()->getConfig('TimedBlocks')
            : [];

        if (!isset($functionConfig[$methodName])) {
            return [];
        }

        $methodBlocks = $functionConfig[$methodName];
        if (isset($methodBlocks['startDate']) || isset($methodBlocks['endDate'])) {
            return [
                'start' => $methodBlocks['startDate'] ?? '',
                'end' => $methodBlocks['endDate'] ?? '',
            ];
        }
        if (isset($methodBlocks['recurringStart']) && isset($methodBlocks['recurringEnd'])) {
            return [
                'start' => $methodBlocks['recurringStart'],
                'end' => $methodBlocks['recurringEnd'],
            ];
        }
        return [];
    }
}
