<?php

/**
 * Content loader view helper
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

/**
 * Content loader view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ContentLoader extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Content loader
     *
     * @var \VuFind\Content\Loader
     */
    protected $loader;

    /**
     * Constructor
     *
     * @param \VuFind\Content\Loader $loader Content loader
     */
    public function __construct(\VuFind\Content\Loader $loader)
    {
        $this->loader = $loader;
    }

    /**
     * Do the actual work of loading the notes.
     *
     * @param string $isbn ISBN of book to find notes for
     *
     * @return array
     */
    public function __invoke($isbn)
    {
        return $this->loader->loadByIsbn($isbn);
    }
}
