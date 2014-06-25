<?php
/**
 * Abstract base for Syndetics content loader plug-ins.
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
use DOMDocument;

/**
 * Abstract base for Syndetics content loader plug-ins.
 *
 * @category VuFind2
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
abstract class AbstractSyndetics extends AbstractBase
{
    /**
     * Use SSL URLs?
     *
     * @var bool
     */
    protected $useSSL;

    /**
     * Use Syndetics plus?
     *
     * @var bool
     */
    protected $usePlus;

    /**
     * Constructor
     *
     * @param bool $useSSL  Use SSL URLs?
     * @param bool $usePlus Use Syndetics Plus?
     */
    public function __construct($useSSL = false, $usePlus = false)
    {
        $this->useSSL = $useSSL;
        $this->usePlus = $usePlus;
    }

    /**
     * Get the Syndetics URL for making a request.
     *
     * @param string $isbn ISBN to load
     * @param string $id   Client ID
     * @param string $file File to request
     * @param string $type Type parameter
     *
     * @return string
     */
    protected function getIsbnUrl($isbn, $id, $file = 'index.xml', $type = 'rw12,h7')
    {
        $baseUrl = $this->useSSL
            ? 'https://secure.syndetics.com' : 'http://syndetics.com';
        $url = $baseUrl . '/index.aspx?isbn=' . $isbn
            . '/' . $file . '&client=' . $id . '&type=' . $type;
        if ($this->logger) {
            $this->logger->debug('Syndetics request: ' . $url);
        }
        return $url;
    }

    /**
     * Turn an XML response into a DOMDocument object.
     *
     * @param string $xml XML to load.
     *
     * @return DOMDocument|bool Document on success, false on failure.
     */
    protected function xmlToDOMDocument($xml)
    {
        $dom = new DOMDocument();
        return $dom->loadXML($xml) ? $dom : false;
    }
}
