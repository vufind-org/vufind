<?php

/**
 * Component view helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\View\Helper\Root;

use function strlen;

/**
 * Component view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Component extends \VuFind\View\Helper\Root\Component
{
    public const BC_REPLACEMENTS = [
        '@@molecules/containers/finna-panel' => 'finna-panel',
    ];

    /**
     * Expand path and render template
     *
     * @param string $name   Component name that matches a template
     * @param array  $params Data for the component template
     *
     * @return string
     */
    public function __invoke(string $name, $params = []): string
    {
        // Backwards compatibility support.
        foreach (self::BC_REPLACEMENTS as $needle => $replacement) {
            if (str_starts_with($name, $needle)) {
                $name = $replacement . substr($name, strlen($needle));
            }
        }
        return parent::__invoke($name, $params);
    }
}
