<?php

/**
 * Reviews tab
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */

namespace VuFind\RecordTab;

use VuFind\Content\Loader;

/**
 * Reviews tab
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
abstract class AbstractContent extends AbstractBase
{
    /**
     * Content loader
     *
     * @var Loader
     */
    protected $loader;

    /**
     * Should we hide the tab if no content is found?
     *
     * @var bool
     */
    protected $hideIfEmpty;

    /**
     * Cache for results.
     *
     * @var array
     */
    protected $results = null;

    /**
     * Constructor
     *
     * @param Loader $loader      Content loader (null to disable)
     * @param bool   $hideIfEmpty Should we hide the tab if no content is found?
     * (Note that turning this on has performance implications).
     */
    public function __construct(Loader $loader = null, $hideIfEmpty = false)
    {
        $this->loader = $loader;
        $this->hideIfEmpty = $hideIfEmpty;
    }

    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        // Depending on the "hide if empty" setting, we either want to check if
        // the API results are empty (an expensive operation) or if we have an
        // ISBN that can be used to retrieve API results (much less expensive,
        // but also less effective at consistently suppressing empty tabs).
        $check = $this->hideIfEmpty
            ? $this->getContent()
            : $this->getRecordDriver()->tryMethod('getCleanISBN');
        return !(null === $this->loader || empty($check));
    }

    /**
     * Get content for ISBN.
     *
     * @return array
     */
    public function getContent()
    {
        if (null === $this->results) {
            $isbn = $this->getRecordDriver()->tryMethod('getCleanISBN');
            $this->results = (null === $this->loader || empty($isbn))
                ? [] : $this->loader->loadByIsbn($isbn);
        }
        return $this->results;
    }
}
