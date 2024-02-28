<?php

/**
 * Abstract base for cover loader plug-ins.
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
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Content;

/**
 * Abstract base for cover loader plug-ins.
 *
 * @category VuFind
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
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
     * Does this plugin support ISMNs?
     *
     * @var bool
     */
    protected $supportsIsmn = false;

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
     * Does this plugin support national bibliographies number?
     *
     * @var bool
     */
    protected $supportsNbn = false;

    /**
     * Does this plugin support getting cover by local id?
     *
     * @var bool
     */
    protected $supportsRecordid = false;

    /**
     * Does this plugin support getting cover by UUID (Universally unique
     * identifier)?
     *
     * @var bool
     */
    protected $supportsUuid = false;

    /**
     * Are we allowed to cache images from this source?
     *
     * @var bool
     */
    protected $cacheAllowed = false;

    /**
     * Use direct urls as image urls. When set to true, direct urls to content cover
     * provider will be used in interface instead internal Cover/Show urls.
     *
     * @var bool
     */
    protected $directUrls = false;

    /**
     * Are backlinks to source of cover mandatory?
     *
     * @var array
     */
    protected $mandatoryBacklinkLocations = [];

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
     * Use direct urls? (Or proxied urls)
     *
     * @return bool
     */
    public function useDirectUrls()
    {
        return $this->directUrls;
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
            || ($this->supportsIsmn && isset($ids['ismn']))
            || ($this->supportsOclc && isset($ids['oclc']))
            || ($this->supportsUpc && isset($ids['upc']))
            || ($this->supportsNbn && isset($ids['nbn']))
            || ($this->supportsRecordid && isset($ids['recordid']))
            || ($this->supportsUuid && isset($ids['uuid']));
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

    /**
     * Get cover metadata for a particular API key and set of IDs (or empty array).
     *
     * @param string $key  API key
     * @param string $size Size of image to load (small/medium/large)
     * @param array  $ids  Associative array of identifiers (keys may include 'isbn'
     * pointing to an ISBN object, 'issn' pointing to a string and 'oclc' pointing
     * to an OCLC number string)
     *
     * @return array Array with keys: url, backlink_url, backlink_text
     */
    public function getMetadata(?string $key, string $size, array $ids)
    {
        $url = $this->getUrl($key, $size, $ids);
        return $url ? ['url' => $url] : [];
    }

    /**
     * Which location are mandatory for backlinks, available locations are the same
     * as used for cover size determination, see coversize setting in [Content]
     * section of config.ini
     *
     * @return array
     */
    public function getMandatoryBacklinkLocations(): array
    {
        return $this->mandatoryBacklinkLocations;
    }
}
