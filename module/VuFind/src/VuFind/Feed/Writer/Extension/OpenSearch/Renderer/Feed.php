<?php

/**
 * Laminas\Feed\Renderer\Feed extension for Open Search
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Feed_Plugins
 * @author   Sebastian Cuy <sebastian.cuy@uni-koeln.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Feed\Writer\Extension\OpenSearch\Renderer;

use DOMDocument;
use DOMElement;
use Laminas\Feed\Writer\Extension\AbstractRenderer;

/**
 * Laminas\Feed\Renderer\Feed extension for Open Search
 *
 * @category VuFind
 * @package  Feed_Plugins
 * @author   Sebastian Cuy <sebastian.cuy@uni-koeln.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Feed extends AbstractRenderer
{
    /**
     * Set to TRUE if a rendering method actually renders something. This
     * is used to prevent premature appending of a XML namespace declaration
     * until an element which requires it is actually appended.
     *
     * @var bool
     */
    protected $called = false;

    /**
     * Render feed
     *
     * @return void
     */
    public function render()
    {
        $this->setTotalResults($this->dom, $this->base);
        $this->setStartIndex($this->dom, $this->base);
        $this->setItemsPerPage($this->dom, $this->base);
        $this->setQuery($this->dom, $this->base);
        $this->setLinks($this->dom, $this->base);
        if ($this->called) {
            $this->_appendNamespaces();
        }
    }

    /**
     * Append feed namespaces
     *
     * @return void
     */
    // @codingStandardsIgnoreStart
    protected function _appendNamespaces()
    {
        // @codingStandardsIgnoreEnd
        // (We have to ignore coding standards here because the method name has
        // to have an underscore for compatibility w/ parent class)
        $this->getRootElement()->setAttribute(
            'xmlns:opensearch',
            'http://a9.com/-/spec/opensearch/1.1/'
        );
    }

    /**
     * Set total results
     *
     * @param DOMDocument $dom  the dom document
     * @param DOMElement  $root the root element
     *
     * @return void
     */
    protected function setTotalResults(DOMDocument $dom, DOMElement $root)
    {
        $totalResults = $this->getDataContainer()->getOpensearchTotalResults();
        if ($totalResults !== null) {
            $elem = $dom->createElement('opensearch:totalResults');
            $text = $dom->createTextNode($totalResults);
            $elem->appendChild($text);
            $root->appendChild($elem);
            $this->called = true;
        }
    }

    /**
     * Set start index
     *
     * @param DOMDocument $dom  the dom document
     * @param DOMElement  $root the root element
     *
     * @return void
     */
    protected function setStartIndex(DOMDocument $dom, DOMElement $root)
    {
        $startIndex = $this->getDataContainer()->getOpensearchStartIndex();
        if ($startIndex !== null) {
            $elem = $dom->createElement('opensearch:startIndex');
            $text = $dom->createTextNode($startIndex);
            $elem->appendChild($text);
            $root->appendChild($elem);
            $this->called = true;
        }
    }

    /**
     * Set items per page
     *
     * @param DOMDocument $dom  the dom document
     * @param DOMElement  $root the root element
     *
     * @return void
     */
    protected function setItemsPerPage(DOMDocument $dom, DOMElement $root)
    {
        $itemsPerPage = $this->getDataContainer()->getOpensearchItemsPerPage();
        if ($itemsPerPage !== null) {
            $elem = $dom->createElement('opensearch:itemsPerPage');
            $text = $dom->createTextNode($itemsPerPage);
            $elem->appendChild($text);
            $root->appendChild($elem);
            $this->called = true;
        }
    }

    /**
     * Set the query element
     *
     * @param DOMDocument $dom  the dom document
     * @param DOMElement  $root the root element
     *
     * @return void
     */
    protected function setQuery(DOMDocument $dom, DOMElement $root)
    {
        $searchTerms = $this->getDataContainer()->getOpensearchSearchTerms();
        $startIndex = $this->getDataContainer()->getOpensearchStartIndex();
        if (!empty($searchTerms)) {
            $elem = $dom->createElement('opensearch:Query');
            $elem->setAttribute('role', 'request');
            $elem->setAttribute('searchTerms', rawurlencode($searchTerms));
            if ($startIndex !== null) {
                $elem->setAttribute('startIndex', $startIndex);
            }
            $root->appendChild($elem);
            $this->called = true;
        }
    }

    /**
     * Set links
     *
     * @param DOMDocument $dom  the dom document
     * @param DOMElement  $root the root element
     *
     * @return void
     */
    protected function setLinks(DOMDocument $dom, DOMElement $root)
    {
        $links = $this->getDataContainer()->getOpensearchLinks();
        foreach ($links as $link) {
            $elem = $dom->createElement('atom:link');
            if ($link['role'] != null) {
                $elem->setAttribute('rel', $link['role']);
            }
            if ($link['type'] != null) {
                $mime = 'application/' . strtolower($link['type']) . '+xml';
                $elem->setAttribute('type', $mime);
            }
            if ($link['title'] ?? null) {
                $elem->setAttribute('title', $link['title']);
            }
            $elem->setAttribute('href', $link['url']);
            $root->appendChild($elem);
            $this->called = true;
        }
    }
}
