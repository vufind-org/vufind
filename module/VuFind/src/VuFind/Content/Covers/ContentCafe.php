<?php
/**
 * ContentCafe cover content loader.
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
 * ContentCafe cover content loader.
 *
 * @category VuFind2
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class ContentCafe extends \VuFind\Content\AbstractCover
{
    /**
     * API password
     *
     * @var string
     */
    protected $password;

    /**
     * Base URL
     *
     * @var string
     */
    protected $baseURL;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $config Configuration
     */
    public function __construct(\Zend\Config\Config $config)
    {
        $this->password = $config->pw;
        $this->baseURL = isset($config->url)
            ? $config->url : 'http://contentcafe2.btol.com';
        $this->supportsIsbn = $this->cacheAllowed = true;
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
        $size = strtoupper(substr($size, 0, 1));
        if (!isset($ids['isbn'])) {
            return false;
        }
        $isbn = $ids['isbn']->get13();
        return $this->baseURL . '/ContentCafe/Jacket.aspx'
            . "?UserID={$key}&Password={$this->password}&Return=1" .
            "&Type={$size}&Value={$isbn}&erroroverride=1";
    }
}
