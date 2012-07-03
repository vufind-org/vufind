<?php
/**
 * OpenURL view helper
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
namespace VuFind\Theme\Root\Helper;
use VuFind\Config\Reader as ConfigReader, Zend\View\Helper\AbstractHelper;

/**
 * OpenURL view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class OpenUrl extends AbstractHelper
{
    /**
     * Render appropriate UI controls for an OpenURL link.
     *
     * @param string $openUrl The OpenURL to display
     *
     * @return string
     */
    public function __invoke($openUrl)
    {
        // Static counter to ensure that each OpenURL gets a unique ID.
        static $counter = 0;

        $config = ConfigReader::getConfig();
        if (isset($config->OpenURL) && isset($config->OpenURL->url)) {
            // Trim off any parameters (for legacy compatibility -- default config
            // used to include extraneous parameters):
            list($base) = explode('?', $config->OpenURL->url);
        } else {
            $base = false;
        }

        $embed = (isset($config->OpenURL->embed) && !empty($config->OpenURL->embed));
        if ($embed) {
            $counter++;
        }

        // Build parameters needed to display the control:
        $params = array(
            'openUrl' => $openUrl,
            'openUrlBase' => empty($base) ? false : $base,
            'openUrlWindow' => empty($config->OpenURL->window_settings)
                ? false : $config->OpenURL->window_settings,
            'openUrlGraphic' => empty($config->OpenURL->graphic)
                ? false : $config->OpenURL->graphic,
            'openUrlGraphicWidth' => empty($config->OpenURL->graphic_width)
                ? false : $config->OpenURL->graphic_width,
            'openUrlGraphicHeight' => empty($config->OpenURL->graphic_height)
                ? false : $config->OpenURL->graphic_height,
            'openUrlEmbed' => $embed,
            'openUrlId' => $counter
        );

        // Render the subtemplate:
        $contextHelper = $this->getView()->plugin('context');
        return $contextHelper($this->getView())->renderInContext(
            'Helpers/openurl.phtml', $params
        );
    }
}