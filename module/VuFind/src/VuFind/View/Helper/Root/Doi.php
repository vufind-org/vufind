<?php

/**
 * DOI view helper
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

/**
 * DOI view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Doi extends \Laminas\View\Helper\AbstractHelper
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
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * Current RecordDriver
     *
     * @var \VuFind\RecordDriver
     */
    protected $recordDriver;

    /**
     * OpenURL context ('results', 'record' or 'holdings')
     *
     * @var string
     */
    protected $area;

    /**
     * Constructor
     *
     * @param Context                $context Context helper
     * @param \Laminas\Config\Config $config  VuFind OpenURL config
     */
    public function __construct(Context $context, $config = null)
    {
        $this->context = $context;
        $this->config = $config;
    }

    /**
     * Set up context for helper
     *
     * @param \VuFind\RecordDriver $driver The current record driver
     * @param string               $area   DOI context ('results', 'record'
     *  or 'holdings'
     *
     * @return object
     */
    public function __invoke($driver, $area)
    {
        $this->recordDriver = $driver;
        $this->area = $area;
        return $this;
    }

    /**
     * Public method to render the OpenURL template
     *
     * @param bool $imagebased Indicates if an image based link
     * should be displayed or not (null for system default)
     *
     * @return string
     */
    public function renderTemplate($imagebased = null)
    {
        // Build parameters needed to display the control:
        $doi = $this->recordDriver->tryMethod('getCleanDOI');
        $params = compact('doi');

        // Render the subtemplate:
        return ($this->context)($this->getView())
            ->renderInContext('Helpers/doi.phtml', $params);
    }

    /**
     * Does the configuration indicate that we should display DOI links in
     * the specified context?
     *
     * @return bool
     */
    protected function checkContext()
    {
        // Doesn't matter the target area if no resolver is specified:
        if (empty($this->config->resolver)) {
            return false;
        }

        // If a setting exists, return that:
        $key = 'show_in_' . $this->area;
        if (isset($this->config->$key)) {
            return $this->config->$key;
        }

        // If we got this far, use the defaults -- true for results, false for
        // everywhere else.
        return $this->area == 'results';
    }

    /**
     * Public method to check whether OpenURLs are active for current record
     *
     * @return bool
     */
    public function isActive()
    {
        $doi = $this->recordDriver->tryMethod('getCleanDOI');
        return !empty($doi) && $this->checkContext();
    }
}
