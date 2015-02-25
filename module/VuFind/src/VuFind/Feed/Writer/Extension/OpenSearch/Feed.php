<?php
/**
 * Zend\Feed\Feed extension for Open Search
 *
 * PHP version 5
 *
 * Copyright (C) Deutsches Archäologisches Institut 2015.
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
 * @package  Feed_Plugins
 * @author   Sebastian Cuy <sebastian.cuy@uni-koeln.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\Feed\Writer\Extension\OpenSearch;
use Zend\Stdlib\StringUtils,
    Zend\Feed\Uri,
    Zend\Feed\Writer\Extension\ITunes\Feed as ParentFeed;

/**
 * Zend\Feed\Feed extension for Open Search
 *
 * Note: There doesn't seem to be a generic base class for this functionality,
 * and creating a class with no parent blows up due to unexpected calls to
 * Itunes-related functionality.  To work around this, we are extending the
 * equivalent Itunes plugin.  This works fine, but perhaps in future there will
 * be a more elegant way to achieve the same effect.
 *
 * @category VuFind2
 * @package  Feed_Plugins
 * @author   Sebastian Cuy <sebastian.cuy@uni-koeln.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Feed extends ParentFeed
{
    /**
     * Total results
     *
     * @var int
     */
    protected $totalResults = null;

    /**
     * Start index
     *
     * @var int
     */
    protected $startIndex = null;

    /**
     * Items per page
     *
     * @var int
     */
    protected $itemsPerPage = null;

    /**
     * Search terms
     *
     * @var string
     */
    protected $searchTerms = null;

    /**
     * Links
     *
     * @var array
     */
    protected $links = [];

    /**
     * Encoding of all text values
     *
     * @var string
     */
    protected $encoding = 'UTF-8';

    /**
     * The used string wrapper supporting encoding
     *
     * @var StringWrapperInterface
     */
    protected $stringWrapper;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->stringWrapper = StringUtils::getWrapper($this->encoding);
    }

    /**
     * Set feed encoding
     *
     * @param string $enc encoding to set
     *
     * @return Feed
     */
    public function setEncoding($enc)
    {
        $this->stringWrapper = StringUtils::getWrapper($enc);
        $this->encoding      = $enc;
        return $this;
    }
    /**
     * Get feed encoding
     *
     * @return string
     */
    public function getEncoding()
    {
        return $this->encoding;
    }

    /**
     * Set total results
     *
     * @param int $totalResults number to set
     *
     * @return Feed
     */
    public function setOpensearchTotalResults($totalResults)
    {
        $this->totalResults = $totalResults;
        return $this;
    }

    /**
     * Get total results
     *
     * @return int
     */
    public function getOpensearchTotalResults()
    {
        return $this->totalResults;
    }

    /**
     * Set start index
     *
     * @param int $startIndex index to set
     *
     * @return Feed
     */
    public function setOpensearchStartIndex($startIndex)
    {
        $this->startIndex = $startIndex;
        return $this;
    }

    /**
     * Get start index
     *
     * @return int
     */
    public function getOpensearchStartIndex()
    {
        return $this->startIndex;
    }

    /**
     * Set items per page
     *
     * @param int $itemsPerPage number to set
     *
     * @return Feed
     */
    public function setOpensearchItemsPerPage($itemsPerPage)
    {
        $this->itemsPerPage = $itemsPerPage;
        return $this;
    }

    /**
     * Get items per page
     *
     * @return int
     */
    public function getOpensearchItemsPerPage()
    {
        return $this->itemsPerPage;
    }

    /**
     * Set search terms
     *
     * @param string $searchTerms search terms
     *
     * @return Feed
     */
    public function setOpensearchSearchTerms($searchTerms)
    {
        $this->searchTerms = $searchTerms;
        return $this;
    }

    /**
     * Get search terms
     *
     * @return string
     */
    public function getOpensearchSearchTerms()
    {
        return $this->searchTerms;
    }

    /**
     * Add a link
     *
     * @param string $url  the url of the link
     * @param string $role the role of the link
     * @param string $type the mime type of the link
     *
     * @return Feed
     */
    public function addOpensearchLink($url, $role = null, $type = null)
    {
        if (empty($url) || !is_string($url) || !Uri::factory($url)->isValid()) {
            throw new Exception\InvalidArgumentException(
                'Invalid parameter: "url" must be '
                . 'a non-empty string and valid URI/IRI'
            );
        }
        if (!in_array(strtolower($type), ['rss', 'rdf', 'atom'])) {
            throw new Exception\InvalidArgumentException(
                'Invalid parameter: "type"; You must declare the type of '
                . 'feed the link points to, i.e. RSS, RDF or Atom'
            );
        }
        $link = [];
        $link['url'] = $url;
        $link['role'] = $role;
        $link['type'] = $type;
        $this->links[] = $link;
        return $this;
    }

    /**
     * Get the links
     *
     * @return string
     */
    public function getOpensearchLinks()
    {
        return $this->links;
    }

}
