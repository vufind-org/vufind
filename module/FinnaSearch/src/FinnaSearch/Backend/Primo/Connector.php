<?php

/**
 * Primo Central connector.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace FinnaSearch\Backend\Primo;
use Zend\Http\Client as HttpClient;

/**
 * Primo Central connector.
 *
 * @category VuFind2
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class Connector extends \VuFindSearch\Backend\Primo\Connector
{
    /**
     * Translate Primo's XML into array of arrays.
     *
     * @param array $data The raw xml from Primo
     *
     * @return array      The processed response from Primo
     */
    protected function process($data)
    {
        $res = parent::process($data);

        // Load API content as XML objects
        $sxe = new \SimpleXmlElement($data);

        if ($sxe === false) {
            throw new \Exception('Error while parsing the document');
        }

        // Register the 'sear' namespace at the top level to avoid problems:
        $sxe->registerXPathNamespace(
            'sear', 'http://www.exlibrisgroup.com/xsd/jaguar/search'
        );

        // Get the available namespaces. The Primo API uses multiple namespaces.
        // Will be used to navigate the DOM for elements that have namespaces
        $namespaces = $sxe->getNameSpaces(true);

        $docset = $sxe->xpath('//sear:DOC');
        if (empty($docset) && isset($sxe->JAGROOT->RESULT->DOCSET->DOC)) {
            $docset = $sxe->JAGROOT->RESULT->DOCSET->DOC;
        }

        for ($i=0; $i<count($docset); $i++) {
            $doc = $docset[$i];

            $sear = $doc->children($namespaces['sear']);
            if ($openURL = $this->getOpenURL($sear)) {
                $res['documents'][$i]['url'] = $openURL;
            } else {
                unset($res['documents'][$i]['url']);
            }

            $res['documents'][$i]['recordid']
                = 'pci.' . $res['documents'][$i]['recordid'];
        }

        return $res;
    }

    /**
     * Retrieves a document specified by the ID.
     *
     * @param string $recordId  The document to retrieve from the Primo API
     * @param string $inst_code Institution code (optional)
     *
     * @throws \Exception
     * @return string    The requested resource
     */
    public function getRecord($recordId, $inst_code = null)
    {
        $recordId = substr(strstr($recordId, '.'), 1);
        return parent::getRecord($recordId, $inst_code);
    }

    /**
     * Helper function for retrieving the OpenURL link from a Primo result.
     *
     * @param SimpleXmlElement $sear XML-element to search
     *
     * @throws \Exception
     * @return string|false
     */
    protected function getOpenURL($sear)
    {
        if (!empty($sear->LINKS->openurl)) {
            if (($url = $sear->LINKS->openurl) !== '') {
                return $url;
            }
        }

        $attr = $sear->GETIT->attributes();
        if (!empty($attr->GetIt2)) {
            if (($url = (string)$attr->GetIt2) !== '') {
                return $url;
            }
        }

        if (!empty($attr->GetIt1)) {
            if (($url = (string)$attr->GetIt1) !== '') {
                return $url;
            }
        }

        return false;
    }
}
