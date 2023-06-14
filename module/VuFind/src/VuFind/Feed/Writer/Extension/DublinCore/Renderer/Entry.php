<?php

/**
 * Laminas\Feed\Renderer\Entry extension for Dublin Core
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
 * @package  Feed_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Feed\Writer\Extension\DublinCore\Renderer;

use DOMDocument;
use DOMElement;
use Laminas\Feed\Writer\Extension\DublinCore\Renderer\Entry as ParentEntry;

/**
 * Laminas\Feed\Renderer\Entry extension for Dublin Core
 *
 * @category VuFind
 * @package  Feed_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Entry extends ParentEntry
{
    /**
     * Render entry
     *
     * @return void
     */
    public function render()
    {
        if (strtolower($this->getType()) == 'atom') {
            return;
        }
        $this->setDCFormats($this->dom, $this->base);
        $this->setDCDate($this->dom, $this->base);
        parent::render();
    }

    /**
     * Set entry format elements
     *
     * @param DOMDocument $dom  DOM document to update
     * @param DOMElement  $root Root of DOM document
     *
     * @return void
     */
    protected function setDCFormats(DOMDocument $dom, DOMElement $root)
    {
        $dcFormats = $this->getDataContainer()->getDCFormats();
        if (empty($dcFormats)) {
            return;
        }
        foreach ($dcFormats as $data) {
            $format = $this->dom->createElement('dc:format');
            $text = $dom->createTextNode($data);
            $format->appendChild($text);
            $root->appendChild($format);
        }
        $this->called = true;
    }

    /**
     * Set entry date elements
     *
     * @param DOMDocument $dom  DOM document to update
     * @param DOMElement  $root Root of DOM document
     *
     * @return void
     */
    protected function setDCDate(DOMDocument $dom, DOMElement $root)
    {
        $dcDate = $this->getDataContainer()->getDCDate();
        if (empty($dcDate)) {
            return;
        }
        $date = $this->dom->createElement('dc:date');
        $text = $dom->createTextNode($dcDate);
        $date->appendChild($text);
        $root->appendChild($date);
        $this->called = true;
    }
}
