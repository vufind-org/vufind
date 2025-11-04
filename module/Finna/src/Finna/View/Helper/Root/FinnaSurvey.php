<?php

/**
 * Finna survey view helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * @link     http://vufind.org   Main Site
 */

namespace Finna\View\Helper\Root;

/**
 * Finna survey view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class FinnaSurvey extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Configuration
     *
     * @var \VuFind\Config\Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param \VuFind\Config\Config $config Configuration
     */
    public function __construct(\VuFind\Config\Config $config)
    {
        $this->config = $config;
    }

    /**
     * Render survey.
     *
     * @return string
     */
    public function render()
    {
        return $this->getView()->render(
            'Helpers/finna-survey.phtml',
            ['url' => $this->config->FinnaSurvey->url]
        );
    }

    /**
     * Check if survey is enabled
     *
     * @return bool
     */
    public function isEnabled()
    {
        return isset($this->config->FinnaSurvey->enabled)
            && $this->config->FinnaSurvey->enabled
            && !empty($this->config->FinnaSurvey->url);
    }
}
