<?php
/**
 * VuFind XSLT wrapper
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
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/ Wiki
 */
namespace VuFind\XSLT;
use DOMDocument, XSLTProcessor;

/**
 * VuFind XSLT wrapper
 *
 * @category VuFind2
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/ Wiki
 */
class Processor
{
    /**
     * Perform an XSLT transformation and return the results.
     *
     * @param string $xslt Name of stylesheet (in application/xsl directory)
     * @param string $xml  XML to transform with stylesheet
     *
     * @return string      Transformed XML
     */
    public static function process($xslt, $xml)
    {
        $style = new DOMDocument();
        // TODO: support local overrides
        $style->load(APPLICATION_PATH . '/module/VuFind/xsl/' . $xslt);
        $xsl = new XSLTProcessor();
        $xsl->importStyleSheet($style);
        $doc = new DOMDocument();
        if ($doc->loadXML($xml)) {
            return $xsl->transformToXML($doc);
        }
        return '';
    }
}
