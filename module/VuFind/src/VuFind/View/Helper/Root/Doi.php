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

use VuFind\Config\Config;
use VuFind\RecordDriver\AbstractBase as RecordDriver;

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
     * Instance counter (used for keeping track of records)
     *
     * @var int
     */
    protected $counter = 0;

    /**
     * Supported identifier types
     *
     * @var string[]
     */
    protected $supportedIdentifiers = ['doi', 'isbn', 'issn'];

    /**
     * Constructor
     *
     * @param Context $context Context helper
     * @param array   $config  Identifier-based linking configuration settings
     */
    public function __construct(protected Context $context, protected array $config = [])
    {
        if (!empty($config['supportedIdentifiers'])) {
            $this->supportedIdentifiers = $config['supportedIdentifiers'];
        }
    }

    /**
     * Set up context for helper
     *
     * @param RecordDriver $driver The current record driver
     * @param string       $area   DOI context ('results', 'record' or 'holdings')
     *
     * @return static
     */
    public function __invoke($driver, $area)
    {
        $this->recordDriver = $driver;
        $this->area = $area;
        return $this;
    }

    /**
     * Get all available identifiers.
     *
     * @return array
     */
    protected function getIdentifiers(): array
    {
        $ids = [];
        if (in_array('doi', $this->supportedIdentifiers)) {
            $ids['doi'] = $this->recordDriver->tryMethod('getCleanDOI');
        }
        if (in_array('isbn', $this->supportedIdentifiers)) {
            $ids['isbn'] = $this->recordDriver->tryMethod('getCleanISBN');
        }
        if (in_array('issn', $this->supportedIdentifiers)) {
            $ids['issn'] = $this->recordDriver->tryMethod('getCleanISSN');
        }
        return $ids;
    }

    /**
     * Public method to render the OpenURL template
     *
     * @return string
     */
    public function renderTemplate()
    {
        // Build parameters needed to display the control:
        $instance = $this->counter++;
        $params = $this->getIdentifiers() + compact('instance');

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
        if (empty($this->config['resolver'])) {
            return false;
        }

        // If a setting exists, return that:
        $key = 'show_in_' . $this->area;
        if (isset($this->config[$key])) {
            return $this->config[$key];
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
        $ids = $this->getIdentifiers();
        $hasId = false;
        foreach ($ids as $id) {
            if ($id !== null) {
                $hasId = true;
                break;
            }
        }
        return $hasId && $this->checkContext();
    }
}
