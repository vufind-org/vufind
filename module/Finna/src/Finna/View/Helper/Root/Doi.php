<?php

/**
 * DOI view helper (for legacy back-compatibility)
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
 * Copyright (C) The National Library of Finland 2025.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\View\Helper\Root;

/**
 * DOI view helper (for legacy back-compatibility)
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
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
     * Context ('results', 'record' or 'holdings')
     *
     * @var string
     */
    protected $context;

    /**
     * Rendered template
     *
     * @var ?string
     */
    protected $renderedTemplate = null;

    /**
     * Set up context for helper
     *
     * @param \VuFind\RecordDriver $driver  The current record driver
     * @param string               $context DOI context ('results', 'record'
     *  or 'holdings'
     *
     * @return object
     */
    public function __invoke($driver, $context)
    {
        $this->recordDriver = $driver;
        $this->context = $context;
        $this->renderedTemplate = null;
        return $this;
    }

    /**
     * Public method to render the template
     *
     * @return string
     */
    public function renderTemplate()
    {
        if (null === $this->renderedTemplate) {
            $linker = $this->getView()->plugin('identifierLinker');
            $this->renderedTemplate = $linker($this->recordDriver, $this->context);
        }
        return $this->renderedTemplate;
    }

    /**
     * Public method to check whether the linker is active for current record
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->renderTemplate() !== '';
    }
}
