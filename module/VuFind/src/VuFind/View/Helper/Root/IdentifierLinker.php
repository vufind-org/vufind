<?php

/**
 * IdentifierLinker view helper
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

use VuFind\RecordDriver\AbstractBase as RecordDriver;

use function in_array;

/**
 * IdentifierLinker view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class IdentifierLinker extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Instance counter (used for keeping track of records)
     *
     * @var int
     */
    protected int $counter = 0;

    /**
     * Supported identifier types
     *
     * @var string[]
     */
    protected array $supportedIdentifiers = ['doi', 'isbn', 'issn'];

    /**
     * Constructor
     *
     * @param Context $contextHelper Context helper
     * @param array   $config        Identifier-based linking configuration settings
     */
    public function __construct(protected Context $contextHelper, protected array $config = [])
    {
        if (!empty($config['supportedIdentifiers'])) {
            $this->supportedIdentifiers = $config['supportedIdentifiers'];
        }
    }

    /**
     * Display identifier links (or blank string, if not active).
     *
     * @param RecordDriver $driver  The current record driver
     * @param string       $context Display context ('results', 'record' or 'holdings')
     *
     * @return string
     */
    public function __invoke(RecordDriver $driver, string $context): string
    {
        return $this->isActive($driver, $context) ? $this->renderTemplate($driver) : '';
    }

    /**
     * Get all available identifiers.
     *
     * @param RecordDriver $driver The current record driver
     *
     * @return array
     */
    protected function getIdentifiers(RecordDriver $driver): array
    {
        $ids = [];
        if (in_array('doi', $this->supportedIdentifiers) && $doi = $driver->tryMethod('getCleanDOI')) {
            $ids['doi'] = $doi;
        }
        if (in_array('isbn', $this->supportedIdentifiers) && $isbn = $driver->tryMethod('getCleanISBN')) {
            $ids['isbn'] = $isbn;
        }
        if (in_array('issn', $this->supportedIdentifiers) && $issn = $driver->tryMethod('getCleanISSN')) {
            $ids['issn'] = $issn;
        }
        return $ids;
    }

    /**
     * Render the identifier links template
     *
     * @param RecordDriver $driver The current record driver
     *
     * @return string
     */
    protected function renderTemplate(RecordDriver $driver): string
    {
        // Build parameters needed to display the control:
        $instance = $this->counter++;
        $params = $this->getIdentifiers($driver) + compact('instance');

        // Render the subtemplate:
        return ($this->contextHelper)($this->getView())
            ->renderInContext('Helpers/identifierLinks.phtml', $params);
    }

    /**
     * Does the configuration indicate that we should display identifier links in
     * the specified context?
     *
     * @param string $context Display context ('results', 'record' or 'holdings')
     *
     * @return bool
     */
    protected function checkContext(string $context): bool
    {
        // Doesn't matter the target context if no resolver is specified:
        if (empty($this->config['resolver'])) {
            return false;
        }

        // If a setting exists, return that:
        $key = 'show_in_' . $context;
        if (isset($this->config[$key])) {
            return $this->config[$key];
        }

        // If we got this far, use the defaults -- true for results, false for
        // everywhere else.
        return $context == 'results';
    }

    /**
     * Check whether identifier links are active for current record
     *
     * @param RecordDriver $driver  The current record driver
     * @param string       $context Display context ('results', 'record' or 'holdings')
     *
     * @return bool
     */
    protected function isActive(RecordDriver $driver, string $context): bool
    {
        $ids = $this->getIdentifiers($driver);
        return !empty($ids) && $this->checkContext($context);
    }
}
