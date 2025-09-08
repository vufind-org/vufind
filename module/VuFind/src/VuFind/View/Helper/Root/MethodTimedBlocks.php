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
     * Returns a display string of timed blocks from driver configuration
     *
     * @param string $methodName        Method to check
     * @param string $methodDisplayName Method display name translation key (Defaults to "This feature")
     *
     * @return string
     */
    public function __invoke(
        string $methodName,
        string $methodDisplayName = '',
    ): string {
        $ils = $this->getView()->plugin('ils');
        if ($ils()->isMethodBlocked($methodName)) {
            $methodBlocks = $ils()->getMethodTimedBlocks($methodName);
            $transEsc = $this->getView()->plugin('transEsc');
            $dateTime = $this->getView()->plugin('dateTime');
            $transParams = [
                '%%service%%' => $methodDisplayName
                    ? $transEsc($methodDisplayName)
                    : $transEsc('default_service_description'),
            ];

            if (!$methodBlocks['recurring']) {
                $end = $methodBlocks['end']
                    ? $dateTime->convertToDisplayDate('U', $methodBlocks['end'])
                    : '';
                $transParams['%%end%%'] = $end;

                if ($end) {
                    return $transEsc('service_blocked_until', $transParams);
                } else {
                    return $transEsc('service_blocked', $transParams);
                }
            } else {
                $end = $dateTime->convertToDisplayTime('U', $methodBlocks['end']);
                $transParams['%%end%%'] = $end;
                return $transEsc('service_blocked_until', $transParams);
            }
        }
        return '';
    }
}
