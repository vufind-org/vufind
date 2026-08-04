<?php

/**
 * Relais view helper.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Renderer\RendererInterface;
use VuFind\Config\Config;
use VuFind\RecordDriver\AbstractBase as RecordDriver;

/**
 * Relais view helper.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Relais
{
    /**
     * Constructor.
     *
     * @param ?Config           $config   Relais configuration (or null if none found)
     * @param string            $loginUrl Login base URL
     * @param RendererInterface $view     View renderer
     * @param TransEsc          $transEsc TransEsc view helper
     */
    public function __construct(
        protected ?Config $config,
        protected string $loginUrl,
        protected RendererInterface $view,
        protected TransEsc $transEsc
    ) {
    }

    /**
     * Create a Relais search link from a record driver.
     *
     * @param RecordDriver $driver Record driver
     *
     * @return string
     */
    public function getSearchLink($driver)
    {
        // Get data elements:
        $isbn = $driver->tryMethod('getCleanISBN');
        $title = $driver->tryMethod('getShortTitle');
        if (empty($title)) {
            $title = $driver->tryMethod('getTitle');
        }
        $mainAuthor = $driver->tryMethod('getPrimaryAuthor');

        // Assemble and return URL:
        $separator = strstr($this->loginUrl, '?') === false ? '?' : '&';
        $url = $this->loginUrl . $separator . 'query='
            . ($isbn ? 'isbn%3D' . rawurlencode($isbn) : 'ti%3D'
            . rawurlencode($title));
        if ($mainAuthor) {
            $url .= '%20and%20au%3D' . rawurlencode($mainAuthor);
        }
        return $url;
    }

    /**
     * Render a button if Relais is active.
     *
     * @param ?RecordDriver $driver Record driver
     *
     * @return string
     */
    public function renderButtonIfActive($driver = null)
    {
        // Case 1: API enabled:
        if ($this->config->apikey ?? false) {
            return $this->view->render('relais/button.phtml');
        }
        // Case 2: Search links enabled:
        if ($this->config->loginUrl ?? false && $driver) {
            return '<a href="' . htmlspecialchars($this->getSearchLink($driver))
                . '" target="new">' . ($this->transEsc)('relais_search')
                . '</a>';
        }
        // Case 3: Nothing enabled:
        return '';
    }

    /**
     * Make helper invokable.
     *
     * @return static
     */
    public function __invoke(): static
    {
        return $this;
    }
}
