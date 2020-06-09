<?php

/**
 * Prepend the site title from config.ini if it exists.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2016.
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
 * @author   Aleksi Turpeinen <aleksi.turpeinen@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

/**
 * Prepend the site title from config.ini if it exists.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Aleksi Turpeinen <aleksi.turpeinen@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class HeadTitle extends \Laminas\View\Helper\HeadTitle
{
    /**
     * Main configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param \Laminas\Config\Config $config Main configuration
     */
    public function __construct(\Laminas\Config\Config $config)
    {
        $this->config = $config;
    }

    /**
     * Render title string
     *
     * @return string
     */
    public function renderTitle()
    {
        $output = parent::renderTitle();
        if (isset($this->config['Site']['title'])) {
            $title = $this->config['Site']['title'];
            if ($this->autoEscape) {
                $title = $this->escape($title);
            }
            if ($output) {
                $output .= " | $title";
            } else {
                $output = $title;
            }
        }
        return $output;
    }
}
