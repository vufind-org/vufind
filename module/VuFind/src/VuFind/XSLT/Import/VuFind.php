<?php

/**
 * XSLT importer support methods.
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Import_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing Wiki
 */

namespace VuFind\XSLT\Import;

use DOMDocument;

use function count;
use function in_array;
use function is_callable;
use function strlen;

/**
 * XSLT support class -- all methods of this class must be public and static;
 * they will be automatically made available to your XSL stylesheet for use
 * with the php:function() function.
 *
 * @category VuFind
 * @package  Import_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing Wiki
 */
class VuFind
{
    /**
     * ISO8601 date format string
     *
     * @var string
     */
    protected const ISO8601_FORMAT = 'Y-m-d\TH:i:s\Z';

    /**
     * Service locator
     *
     * @var ServiceLocatorInterface
     */
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
     * Get the change tracker service object.
     *
     * @return \VuFind\Db\Service\ChangeTrackerServiceInterface
     */
    public static function getChangeTracker()
    {
        return static::$serviceLocator->get(\VuFind\Db\Service\PluginManager::class)
            ->get(\VuFind\Db\Service\ChangeTrackerServiceInterface::class);
    }

    /**
     * Get a configuration file.
     *
     * @param string $config Configuration name
     *
     * @return \Laminas\Config\Config
     */
    public static function getConfig($config = 'config')
    {
        return static::$serviceLocator->get(\VuFind\Config\PluginManager::class)
            ->get($config);
    }

    /**
     * Get the date/time of the first time this record was indexed.
     *
     * @param string $core Solr core holding this record.
     * @param string $id   Record ID within specified core.
     * @param string $date Date record was last modified.
     *
     * @return string      First index date/time.
     */
    public static function getFirstIndexed($core, $id, $date)
    {
        $date = strtotime($date);
        $row = static::getChangeTracker()->index($core, $id, $date);
        return $row->getFirstIndexed()->format(self::ISO8601_FORMAT);
    }

    /**
     * Get the date/time of the most recent time this record was indexed.
     *
     * @param string $core Solr core holding this record.
     * @param string $id   Record ID within specified core.
     * @param string $date Date record was last modified.
     *
     * @return string      Latest index date/time.
     */
    public static function getLastIndexed($core, $id, $date)
    {
        $date = strtotime($date);
        $row = static::getChangeTracker()->index($core, $id, $date);
        return $row->getLastIndexed()->format(self::ISO8601_FORMAT);
    }

    /**
     * Harvest the contents of a text file for inclusion in the output.
     *
     * @param string $url URL of file to retrieve.
     *
     * @return string     file contents.
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
     * Read parser method from fulltext.ini
     *
     * @return string Name of parser to use (i.e. Aperture or Tika)
     */
    public static function getParser()
    {
        $settings = static::getConfig('fulltext');

        // Is user preference explicitly set?
        if (isset($settings->General->parser)) {
            return $settings->General->parser;
        }

        // Is Aperture enabled?
        if (isset($settings->Aperture->webcrawler)) {
            return 'Aperture';
        }

        // Is Tika enabled?
        if (isset($settings->Tika->path)) {
            return 'Tika';
        }

        // If we got this far, no parser is available:
        return 'None';
    }

    /**
     * Call parsing method based on parser setting in fulltext.ini
     *
     * @param string $url URL to harvest
     *
     * @return string     Text contents of URL
     */
    public static function harvestWithParser($url)
    {
        $parser = static::getParser();
        switch (strtolower($parser)) {
            case 'aperture':
                return static::harvestWithAperture($url);
            case 'tika':
                return static::harvestWithTika($url);
            default:
                // Ignore unrecognized parser option:
                return '';
        }
    }

    /**
     * Generic method for building Aperture Command
     *
     * @param string $input  name of input file | url
     * @param string $output name of output file
     * @param string $method webcrawler | filecrawler
     *
     * @return string        command to be executed
     */
    public static function getApertureCommand(
        $input,
        $output,
        $method = 'webcrawler'
    ) {
        // get the path to our sh/bat from the config
        $settings = static::getConfig('fulltext');
        if (!isset($settings->Aperture->webcrawler)) {
            return '';
        }
        $cmd = $settings->Aperture->webcrawler;

        // if we're using another method - substitute that into the path
        $cmd = ($method != 'webcrawler')
            ? str_replace('webcrawler', $method, $cmd) : $cmd;

        // return the full command
        return "{$cmd} -o {$output} -x {$input}";
    }

    /**
     * Strip illegal XML characters from a string.
     *
     * @param string $in String to process
     *
     * @return string
     */
    public static function stripBadChars($in)
    {
        $badChars = '/[^\\x0009\\x000A\\x000D\\x0020-\\xD7FF\\xE000-\\xFFFD]/';
        return preg_replace($badChars, ' ', $in);
    }

    /**
     * Harvest the contents of a document file (PDF, Word, etc.) using Aperture.
     * This method will only work if Aperture is properly configured in the
     * fulltext.ini file. Without proper configuration, this will simply return an
     * empty string.
     *
     * @param string $url    URL of file to retrieve.
     * @param string $method webcrawler | filecrawler
     *
     * @return string        text contents of file.
     */
    public static function harvestWithAperture($url, $method = 'webcrawler')
    {
        // Build a filename for temporary XML storage:
        $xmlFile = tempnam('/tmp', 'apt');

        // Determine the base Aperture command (or fail if it is not configured):
        $aptCmd = static::getApertureCommand($url, $xmlFile, $method);
        if (empty($aptCmd)) {
            return '';
        }

        // Call Aperture:
        exec($aptCmd);

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
        return static::stripBadChars($final);
    }

    /**
     * Generic method for building Tika command
     *
     * @param string $input  url | fileresource
     * @param string $output name of output file
     * @param string $arg    optional Tika arguments
     *
     * @return array         Parameters for proc_open command
     */
    public static function getTikaCommand($input, $output, $arg)
    {
        $settings = static::getConfig('fulltext');
        if (!isset($settings->Tika->path)) {
            return '';
        }
        $tika = $settings->Tika->path;

        // We need to use this method to get the output from STDOUT into the file
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['file', $output, 'w'],
            2 => ['pipe', 'w'],
        ];
        return [
            "java -jar $tika $arg -eUTF8 $input", $descriptorspec, [],
        ];
    }

    /**
     * Harvest the contents of a document file (PDF, Word, etc.) using Tika.
     * This method will only work if Tika is properly configured in the
     * fulltext.ini file. Without proper configuration, this will simply return an
     * empty string.
     *
     * @param string $url URL of file to retrieve.
     * @param string $arg optional argument(s) for Tika
     *
     * @return string     text contents of file.
     */
    public static function harvestWithTika($url, $arg = '--text')
    {
        // Build a filename for temporary XML storage:
        $outputFile = tempnam('/tmp', 'tika');

        // Determine the base Tika command and execute
        $tikaCommand = static::getTikaCommand($url, $outputFile, $arg);
        proc_close(proc_open($tikaCommand[0], $tikaCommand[1], $tikaCommand[2]));

        // If we failed to process the file, give up now:
        if (!file_exists($outputFile)) {
            return '';
        }

        // Extract and decode the full text from the XML:
        $txt = file_get_contents($outputFile);
        @unlink($outputFile);

        return $txt;
    }

    /**
     * Map string using a config file from the translation_maps folder.
     *
     * @param string $in       string to map.
     * @param string $filename filename of map file
     *
     * @return string          mapped text.
     */
    public static function mapString($in, $filename)
    {
        // Load the translation map and send back the appropriate value. Note
        // that PHP's parse_ini_file() function is not compatible with SolrMarc's
        // style of properties map, so we are parsing this manually.
        $map = [];
        $resolver = static::$serviceLocator->get(\VuFind\Config\PathResolver::class);
        $mapFile = $resolver->getConfigPath($filename, 'import/translation_maps');
        foreach ($mapFile ? file($mapFile) : [] as $line) {
            $parts = explode('=', $line, 2);
            if (isset($parts[1])) {
                $key = trim($parts[0]);
                $map[$key] = trim($parts[1]);
            }
        }
        return $map[$in] ?? $in;
    }

    /**
     * Strip articles from the front of the text (for creating sortable titles).
     *
     * @param string $in title to process.
     *
     * @return string    article-stripped text.
     */
    public static function stripArticles($in)
    {
        static $articles = ['a', 'an', 'the'];

        $text = is_callable('mb_strtolower')
            ? mb_strtolower(trim($in), 'UTF-8')
            : strtolower(trim($in));

        foreach ($articles as $a) {
            if (str_starts_with($text, $a . ' ')) {
                $text = substr($text, strlen($a) + 1);
                break;
            }
        }

        return $text;
    }

    /**
     * Strip accents from a string.
     *
     * @param string $str String to process.
     *
     * @return string     Processed string.
     */
    public static function stripAccents(string $str): string
    {
        $tl = \Transliterator::create('Latin-ASCII;');
        return $tl->transliterate($str);
    }

    /**
     * Strip punctuation from a string.
     *
     * @param string $str String to process.
     *
     * @return string     Processed string.
     */
    public static function stripPunctuation(string $str): string
    {
        // Convert strings of spaces and punctuation into single spaces, for
        // consistency with SolrMarc behavior.
        return preg_replace('/[[:punct:]\s]+/', ' ', $str);
    }

    /**
     * Remove single square bracket characters if they are the start and/or end
     * chars (matched or unmatched) and are the only square bracket chars in the
     * string.
     *
     * Ported from SolrMarc's DataUtil class.
     *
     * @param string $str Text string with possible enclosing brackets
     *
     * @return string     Processed string with the brackets removed.
     */
    public static function removeOuterBrackets(string $str): string
    {
        $result = trim($str);
        if (strlen($result) > 0) {
            $openBracketFirst = str_starts_with($result, '[');
            $closeBracketLast = str_ends_with($result, ']');
            $totalLefts = substr_count($result, '[');
            $totalRights = substr_count($result, ']');
            if ($openBracketFirst && $closeBracketLast && $totalLefts === 1 && $totalRights === 1) {
                // only square brackets are at beginning and end
                $result = substr($result, 1, strlen($result) - 2);
            } elseif ($openBracketFirst && $totalRights === 0) {
                // starts with '[' but no ']'; remove open bracket
                $result = substr($result, 1);
            } elseif ($closeBracketLast && $totalLefts === 0) {
                // ends with ']' but no '['; remove close bracket
                $result = substr($result, 0, strlen($result) - 1);
            }
        }
        return $result;
    }

    /**
     * Port of logic from SolrMarc's DataUtil::cleanData method.
     *
     * @param string $str String to process.
     *
     * @return string     Processed string.
     */
    public static function solrMarcStyleCleanData(string $str): string
    {
        $needsPeriodStripping = function ($strToCheck) {
            $noStrippingRegex = [
                '/.*[JS]r\.$/', // don't strip period off of Jr. or Sr.
            ];
            $strippingRegex = [
                '/.*\w\w\.$/',
                '/.*\p{L}\p{L}\.$/',
                // The following regex is unsupported by PHP but retained for reference:
                //'/.*\w\p{InCombiningDiacriticalMarks}?\w\p{InCombiningDiacriticalMarks}?\.$/u',
                '/.*\p{P}\.$/u',
            ];
            foreach ($noStrippingRegex as $regex) {
                if (preg_match($regex, $strToCheck)) {
                    return false;
                }
            }
            foreach ($strippingRegex as $regex) {
                if (preg_match($regex, $strToCheck)) {
                    return true;
                }
            }
            return false;
        };

        $current = $str;
        do {
            $previous = $current;
            $current = trim($current);
            $current = preg_replace('|\s*([,/;:])$|', '', $current);
            if (str_ends_with($current, '.')) {
                if ($needsPeriodStripping($current)) {
                    $current = mb_substr($current, 0, mb_strlen($current, 'UTF-8') - 1, 'UTF-8');
                }
            }
            $current = static::removeOuterBrackets($current);
            if (strlen($current) === 0) {
                return $current;
            }
        } while ($current !== $previous);
        return $current;
    }

    /**
     * Perform text processing roughly equivalent to SolrMarc's titleSortLower
     * feature to allow consistent indexing into the title_sort field.
     *
     * @param string $str String to process.
     *
     * @return string     Processed string.
     */
    public static function titleSortLower(string $str): string
    {
        return mb_strtolower(
            static::solrMarcStyleCleanData(
                static::stripPunctuation(
                    static::stripAccents($str)
                )
            ),
            'UTF-8'
        );
    }

    /**
     * Convert provided nodes into XML and return as text. This is useful for
     * populating the fullrecord field with the raw input XML.
     *
     * @param array $in array of DOMElement objects.
     *
     * @return string   XML as string
     */
    public static function xmlAsText($in)
    {
        // Start building return value:
        $text = '';

        // Extract all text:
        foreach ((array)$in as $current) {
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
     * into XML and return as text. This is useful for
     * populating the fullrecord field with the raw input XML but
     * allow for removal of certain elements (eg: full text field).
     *
     * @param array  $in  array of DOMElement objects.
     * @param string $tag name of tag to remove
     *
     * @return string     XML as string
     */
    public static function removeTagAndReturnXMLasText($in, $tag)
    {
        foreach ((array)$in as $current) {
            $matches = $current->getElementsByTagName($tag);
            foreach ($matches as $match) {
                $current->removeChild($match);
            }
        }

        return static::xmlAsText($in);
    }

    /**
     * Proxy the explode PHP function for use in XSL transformation.
     *
     * @param string $delimiter Delimiter for splitting $string
     * @param string $string    String to split
     *
     * @return DOMDocument
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

    /**
     * Proxy the implode PHP function for use in XSL transformation.
     *
     * @param string $glue   Glue string
     * @param array  $pieces DOM elements to join together.
     *
     * @return string
     */
    public static function implode($glue, $pieces)
    {
        $mapper = function ($dom) {
            return trim($dom->textContent);
        };
        return implode($glue, array_map($mapper, $pieces));
    }

    /**
     * Try to find the best single year or date range in a set of DOM elements.
     * Best is defined as the first value to consist of only YYYY or YYYY-ZZZZ,
     * with no other text. If no "best" match is found, the first value is used.
     *
     * @param array $input DOM elements to search.
     *
     * @return string
     */
    public static function extractBestDateOrRange($input)
    {
        foreach ($input as $current) {
            if (preg_match('/^\d{4}(-\d{4})?$/', $current->textContent)) {
                return $current->textContent;
            }
        }
        return reset($input)->textContent;
    }

    /**
     * Try to find a four-digit year in a set of DOM elements.
     *
     * @param array $input DOM elements to search.
     *
     * @return string
     */
    public static function extractEarliestYear($input)
    {
        $goodMatch = $adequateMatch = '';
        foreach ($input as $current) {
            // Best match -- a four-digit string starting with 1 or 2
            preg_match_all('/[12]\d{3}/', $current->textContent, $matches);
            foreach ($matches[0] as $match) {
                if (empty($goodMatch) || $goodMatch > $match) {
                    $goodMatch = $match;
                }
            }
            // Next best match -- any string of four or fewer digits.
            for ($length = 4; $length > 0; $length--) {
                preg_match_all(
                    '/\d{' . $length . '}/',
                    $current->textContent,
                    $matches
                );
                foreach ($matches[0] as $match) {
                    if (strlen($match) > strlen($adequateMatch)) {
                        $adequateMatch = $match;
                    }
                }
            }
        }
        return empty($goodMatch) ? $adequateMatch : $goodMatch;
    }

    /**
     * Is the provided name inverted ("Last, First") or not ("First Last")?
     *
     * @param string $name Name to check
     *
     * @return bool
     */
    public static function isInvertedName(string $name): bool
    {
        $parts = explode(',', $name);
        // If there are no commas, it's not inverted...
        if (count($parts) < 2) {
            return false;
        }
        // If there are commas, let's see if the last part is a title,
        // in which case it could go either way, so we need to recalculate.
        $lastPart = array_pop($parts);
        $titles = ['jr', 'sr', 'dr', 'mrs', 'ii', 'iii', 'iv'];
        if (in_array(strtolower(trim($lastPart, ' .')), $titles)) {
            return count($parts) > 1;
        }
        return true;
    }

    /**
     * Invert "Firstname Lastname" authors into "Lastname, Firstname."
     *
     * @param string $rawName Raw name
     *
     * @return string
     */
    public static function invertName(string $rawName): string
    {
        // includes the full name, eg.: Bento, Filipe Manuel dos Santos
        $parts = preg_split('/\s+(?=[^\s]+$)/', $rawName, 2);
        if (count($parts) != 2) {
            return $rawName;
        }
        [$fnames, $lname] = $parts;
        return "$lname, $fnames";
    }

    /**
     * Call invertName on all matching elements; return a DOMDocument with a
     * name tag for each inverted name.
     *
     * @param array $input DOM elements to adjust
     *
     * @return DOMDocument
     */
    public static function invertNames($input): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'utf-8');
        foreach ($input as $name) {
            $inverted = self::isInvertedName($name->textContent)
                ? $name->textContent
                : self::invertName($name->textContent);
            $element = $dom->createElement('name');
            $element->nodeValue = htmlspecialchars($inverted);
            $dom->appendChild($element);
        }
        return $dom;
    }
}
