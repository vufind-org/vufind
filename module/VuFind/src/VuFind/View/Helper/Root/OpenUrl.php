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
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\View\Helper\Root;

/**
 * OpenURL view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class OpenUrl extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Context helper
     *
     * @var \VuFind\View\Helper\Root\Context
     */
    protected $context;

    /**
     * VuFind OpenURL configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $config VuFind OpenURL configuration
     */
    public function __construct(\VuFind\View\Helper\Root\Context $context,
        $config = null
    ) {
        $this->context = $context;
        $this->config = $config;
    }

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

        if (null !== $this->config && isset($this->config->url)) {
            // Trim off any parameters (for legacy compatibility -- default config
            // used to include extraneous parameters):
            list($base) = explode('?', $this->config->url);
        } else {
            $base = false;
        }

        $embed = (isset($this->config->embed) && !empty($this->config->embed));
        if ($embed) {
            $counter++;
        }

        // Build parameters needed to display the control:
        $params = array(
            'openUrl' => $openUrl,
            'openUrlBase' => empty($base) ? false : $base,
            'openUrlWindow' => empty($this->config->window_settings)
                ? false : $this->config->window_settings,
            'openUrlGraphic' => empty($this->config->graphic)
                ? false : $this->config->graphic,
            'openUrlGraphicWidth' => empty($this->config->graphic_width)
                ? false : $this->config->graphic_width,
            'openUrlGraphicHeight' => empty($this->config->graphic_height)
                ? false : $this->config->graphic_height,
            'openUrlEmbed' => $embed,
            'openUrlId' => $counter
        );

        // Render the subtemplate:
        return $this->context->__invoke($this->getView())->renderInContext(
            'Helpers/openurl.phtml', $params
        );
    }
}