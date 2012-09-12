<?php
/**
 * XSLT importer support methods.
 *
 * PHP version 5
 *
 * Copyright (c) Demian Katz 2010.
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
 * @package  Import_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/importing_records Wiki
 */
namespace VuFind\XSLT\Import;
use DOMDocument, VuFind\Config\Reader as ConfigReader;

/**
 * XSLT support class -- all methods of this class must be public and static;
 * they will be automatically made available to your XSL stylesheet for use
 * with the php:function() function.
 *
 * @category VuFind2
 * @package  Import_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/importing_records Wiki
 */
class VuFind
{
    protected static $serviceLocator;

    /**
     * Set the service locator.
     *
     * @param ServiceLocatorInterface $serviceLocator Locator to register
     *
     * @return void
     */
    public static function setServiceLocator($serviceLocator)
    {
        static::$serviceLocator = $serviceLocator;
    }

    /**
     * Get the change tracker table object.
     *
     * @return \VuFind\Db\Table\ChangeTracker
     */
    public static function getChangeTracker()
    {
        return static::$serviceLocator->get('DbTablePluginManager')
            ->get('ChangeTracker');
    }

    /**
     * Get the date/time of the first time this record was indexed.
     *
     * @param string $core Solr core holding this record.
     * @param string $id   Record ID within specified core.
     * @param string $date Date record was last modified.
     *
     * @return string      First index date/time.
     * @access public
     */
    public static function getFirstIndexed($core, $id, $date)
    {
        $date = strtotime($date);
        $row = static::getChangeTracker()->index($core, $id, $date);
        $iso8601 = 'Y-m-d\TH:i:s\Z';
        return date($iso8601, strtotime($row->first_indexed));
    }

    /**
     * Get the date/time of the most recent time this record was indexed.
     *
     * @param string $core Solr core holding this record.
     * @param string $id   Record ID within specified core.
     * @param string $date Date record was last modified.
     *
     * @return string      Latest index date/time.
     * @access public
     */
    public static function getLastIndexed($core, $id, $date)
    {
        $date = strtotime($date);
        $row = static::getChangeTracker()->index($core, $id, $date);
        $iso8601 = 'Y-m-d\TH:i:s\Z';
        return date($iso8601, strtotime($row->last_indexed));
    }

    /**
     * Harvest the contents of a text file for inclusion in the output.
     *
     * @param string $url URL of file to retrieve.
     *
     * @return string     file contents.
     * @access public
     */
    public static function harvestTextFile($url)
    {
        // Skip blank URLs:
        if (empty($url)) {
            return '';
        }

        $text = file_get_contents($url);
        if ($text === false) {
            throw new \Exception("Unable to access {$url}.");
        }
        return $text;
    }

    /**
     * Harvest the contents of a document file (PDF, Word, etc.) using Aperture.
     * This method will only work if Aperture is properly configured in the
     * fulltext.ini file.  Without proper configuration, this will simply return
     * an empty string.
     *
     * @param string $url URL of file to retrieve.
     *
     * @return string     text contents of file.
     * @access public
     */
    public static function harvestWithAperture($url)
    {
        // Determine the base Aperture command (or fail if it is not configured):
        $settings = ConfigReader::getConfig('fulltext');
        if (!isset($settings->Aperture->webcrawler)) {
            return '';
        }
        $cmd = $settings->Aperture->webcrawler;

        // Build a filename for temporary XML storage:
        $xmlFile = tempnam('/tmp', 'apt');

        // Call Aperture:
        exec("$cmd -o $xmlFile -x $url");

        // If we failed to process the file, give up now:
        if (!file_exists($xmlFile)) {
            return '';
        }

        // Extract and decode the full text from the XML:
        $xml = file_get_contents($xmlFile);
        @unlink($xmlFile);
        preg_match('/<plainTextContent[^>]*>([^<]*)</ms', $xml, $matches);
        $final = isset($matches[1]) ?
            html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8') : '';

        // Send back what we extracted, stripping out any illegal characters that
        // will prevent XML from generating correctly:
        $badChars = '/[^\\x0009\\x000A\\x000D\\x0020-\\xD7FF\\xE000-\\xFFFD]/';
        return preg_replace($badChars, ' ', $final);
    }

    /**
     * Map string using a config file from the translation_maps folder.
     *
     * @param string $in       string to map.
     * @param string $filename filename of map file
     *
     * @return string          mapped text.
     * @access public
     */
    public static function mapString($in, $filename)
    {
        // Load the translation map and send back the appropriate value.  Note
        // that PHP's parse_ini_file() function is not compatible with SolrMarc's
        // style of properties map, so we are parsing this manually.
        $map = array();
        $mapFile
            = ConfigReader::getConfigPath($filename, 'import/translation_maps');
        foreach (file($mapFile) as $line) {
            $parts = explode('=', $line, 2);
            if (isset($parts[1])) {
                $key = trim($parts[0]);
                $map[$key] = trim($parts[1]);
            }
        }
        return isset($map[$in]) ? $map[$in] : $in;
    }

    /**
     * Strip articles from the front of the text (for creating sortable titles).
     *
     * @param string $in title to process.
     *
     * @return string    article-stripped text.
     * @access public
     */
    public static function stripArticles($in)
    {
        static $articles = array('a', 'an', 'the');

        $text = strtolower(trim($in));

        foreach ($articles as $a) {
            if (substr($text, 0, strlen($a) + 1) == ($a . ' ')) {
                $text = substr($text, strlen($a) + 1);
                break;
            }
        }

        return $text;
    }

    /**
     * Convert provided nodes into XML and return as text.  This is useful for
     * populating the fullrecord field with the raw input XML.
     *
     * @param array $in array of DOMElement objects.
     *
     * @return string   XML as string
     * @access public
     */
    public static function xmlAsText($in)
    {
        // Ensure that $in is an array:
        if (!is_array($in)) {
            $in = array($in);
        }

        // Start building return value:
        $text = '';

        // Extract all text:
        foreach ($in as $current) {
            // Convert DOMElement to SimpleXML:
            $xml = simplexml_import_dom($current);

            // Pull out text:
            $text .= $xml->asXML();
        }

        // Collapse whitespace:
        return $text;
    }

    /**
     * Remove a given tag from the provided nodes, then convert
     * into XML and return as text.  This is useful for
     * populating the fullrecord field with the raw input XML but
     * allow for removal of certain elements (eg: full text field).
     *
     * @param array  $in  array of DOMElement objects.
     * @param string $tag name of tag to remove
     *
     * @return string     XML as string
     * @access public
     */
    public static function removeTagAndReturnXMLasText($in, $tag)
    {
        // Ensure that $in is an array:
        if (!is_array($in)) {
            $in = array($in);
        }

        foreach ($in as $current) {
            $matches = $current->getElementsByTagName($tag);
            foreach ($matches as $match) {
                $current->removeChild($match);
            }
        }

        return self::xmlAsText($in);
    }

    /**
     * Proxy the explode PHP function for use in XSL transformation.
     *
     * @param string $delimiter Delimiter for splitting $string
     * @param string $string    String to split
     *
     * @return DOMDocument
     * @access public
     */
    public static function explode($delimiter, $string)
    {
        $parts = explode($delimiter, $string);
        $dom = new DOMDocument('1.0', 'utf-8');
        foreach ($parts as $part) {
            $element = $dom->createElement('part', $part);
            $dom->appendChild($element);
        }
        return $dom;
    }
}
