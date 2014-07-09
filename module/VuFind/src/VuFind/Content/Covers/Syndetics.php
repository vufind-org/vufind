<?php
/**
 * Syndetics cover content loader.
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
namespace VuFind\Content\Covers;

/**
 * Syndetics cover content loader.
 *
 * @category VuFind2
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Syndetics extends \VuFind\Content\AbstractCover
{
    /**
     * Use SSL URLs?
     *
     * @var bool
     */
    protected $useSSL;

    /**
     * Constructor
     *
     * @param bool $useSSL Use SSL URLs?
     */
    public function __construct($useSSL = false)
    {
        $this->useSSL = $useSSL;
        $this->supportsIsbn = $this->supportsIssn = $this->supportsOclc
            = $this->supportsUpc = $this->cacheAllowed = true;
    }

    /**
     * Get image URL for a particular API key and set of IDs (or false if invalid).
     *
     * @param string $key  API key
     * @param string $size Size of image to load (small/medium/large)
     * @param array  $ids  Associative array of identifiers (keys may include 'isbn'
     * pointing to an ISBN object and 'issn' pointing to a string)
     *
     * @return string|bool
     */
    public function getUrl($key, $size, $ids)
    {
        switch ($size) {
        case 'small':
            $size = 'SC.GIF';
            break;
        case 'medium':
            $size = 'MC.GIF';
            break;
        case 'large':
            $size = 'LC.JPG';
            break;
        }

        $url = $this->useSSL
            ? 'https://secure.syndetics.com' : 'http://syndetics.com';
        $url .= "/index.aspx?type=xw12";
        if (isset($ids['isbn']) && $ids['isbn']->isValid()) {
            $isbn = $ids['isbn']->get13();
            $url .= "&isbn={$isbn}";
        } else {
            $isbn = false;
        }
        if (isset($ids['issn'])) {
            $url .= "&issn={$ids['issn']}";
            $issn = true;
        } else {
            $issn = false;
        }
        if (isset($ids['oclc'])) {
            $url .= "&oclc={$ids['oclc']}";
            $oclc = true;
        } else {
            $oclc = false;
        }
        if (isset($ids['upc'])) {
            $url .= "&upc={$ids['upc']}";
            $upc = true;
        } else {
            $upc = false;
        }
        $url .= "/{$size}&client={$key}";
        return ($isbn || $issn || $oclc || $upc) ? $url : false;
    }
}
