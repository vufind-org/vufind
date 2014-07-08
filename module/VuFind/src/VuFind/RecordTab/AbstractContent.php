<?php
/**
 * Reviews tab
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
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
namespace VuFind\RecordTab;

/**
 * Reviews tab
 *
 * @category VuFind2
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
abstract class AbstractContent extends AbstractBase
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
     * @param \VuFind\Content\Loader $loader Content loader (omit to disable)
     */
    public function __construct(\VuFind\Content\Loader $loader = null)
    {
        $this->loader = $loader;
    }

    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        if (null === $this->loader) {
            return false;
        }
        $isbn = $this->getRecordDriver()->tryMethod('getCleanISBN');
        return !empty($isbn);
    }

    /**
     * Get content for ISBN.
     *
     * @return array
     */
    public function getContent()
    {
        $isbn = $this->getRecordDriver()->tryMethod('getCleanISBN');
        return (null === $this->loader || empty($isbn))
            ? array() : $this->loader->loadByIsbn($isbn);
    }
}