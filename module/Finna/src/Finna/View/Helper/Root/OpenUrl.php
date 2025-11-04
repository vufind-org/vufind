<?php

/**
 * OpenURL view helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2014.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\View\Helper\Root;

/**
 * OpenURL view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class OpenUrl extends \VuFind\View\Helper\Root\OpenUrl
{
    /**
     * Public method to render the OpenURL more options template
     *
     * @return string
     */
    public function renderMoreOptions()
    {
        if (null !== $this->config && !empty($this->config->url)) {
            // Trim off any parameters (for legacy compatibility -- default config
            // used to include extraneous parameters):
            [$base] = explode('?', $this->config->url);
        } else {
            return '';
        }

        // Build parameters needed to display the control:
        $params = [
            'openUrl' => $this->recordDriver->getOpenUrl(),
            'openUrlBase' => empty($base) ? false : $base,
        ];

        // Render the subtemplate:
        return ($this->context)($this->getView())->renderInContext(
            'Helpers/openurl-moreoptions.phtml',
            $params
        );
    }
}
