<?php
/**
 * Abstract base for cover loader plug-ins.
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
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\Content;

/**
 * Abstract base for cover loader plug-ins.
 *
 * @category VuFind2
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
abstract class AbstractCover
{
    /**
     * Does this plugin support ISBNs?
     *
     * @var bool
     */
    protected $supportsIsbn = false;

    /**
     * Does this plugin support ISSNs?
     *
     * @var bool
     */
    protected $supportsIssn = false;

    /**
     * Does this plugin support OCLC numbers?
     *
     * @var bool
     */
    protected $supportsOclc = false;

    /**
     * Does this plugin support UPC numbers?
     *
     * @var bool
     */
    protected $supportsUpc = false;

    /**
     * Are we allowed to cache images from this source?
     *
     * @var bool
     */
    protected $cacheAllowed = false;

    /**
     * Are we allowed to cache images from this source?
     *
     * @return bool
     */
    public function isCacheAllowed()
    {
        return $this->cacheAllowed;
    }

    /**
     * Does this plugin support the provided ID array?
     *
     * @param array $ids IDs that will later be sent to load() -- see below.
     *
     * @return bool
     */
    public function supports($ids)
    {
        return
            ($this->supportsIsbn && isset($ids['isbn']))
            || ($this->supportsIssn && isset($ids['issn']))
            || ($this->supportsOclc && isset($ids['oclc']))
            || ($this->supportsUpc && isset($ids['upc']));
    }

    /**
     * Get image URL for a particular API key and set of IDs (or false if invalid).
     *
     * @param string $key  API key
     * @param string $size Size of image to load (small/medium/large)
     * @param array  $ids  Associative array of identifiers (keys may include 'isbn'
     * pointing to an ISBN object, 'issn' pointing to a string and 'oclc' pointing
     * to an OCLC number string)
     *
     * @return string|bool
     */
    abstract public function getUrl($key, $size, $ids);
}
