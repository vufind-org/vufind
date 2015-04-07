<?php
/**
 * OpenURL view helper
 *
 * PHP version 5
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

/**
 * OpenURL view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class OpenUrl extends \VuFind\View\Helper\Root\OpenUrl
{
    /**
     * Render appropriate UI controls for an OpenURL link.
     *
     * @param string  $openUrl         The OpenURL to display
     * @param boolean $onlyMoreOptions If true, returns a static link to
     * resolver service.
     *
     * @return string
     */
    public function __invoke($openUrl, $onlyMoreOptions = false)
    {
        if (!$onlyMoreOptions) {
            return parent::__invoke($openUrl);
        } else {
            if (null !== $this->config && isset($this->config->url)) {
                // Trim off any parameters (for legacy compatibility -- default
                // config used to include extraneous parameters):
                list($base) = explode('?', $this->config->url);

                // Build parameters needed to display the control:
                $params = [
                    'openUrl' => $openUrl,
                    'openUrlBase' => empty($base) ? false : $base
                ];

                // Render the subtemplate:
                return $this->context->renderInContext(
                    'Helpers/openurl-moreoptions.phtml', $params
                );
            }

            return false;
        }
    }
}
