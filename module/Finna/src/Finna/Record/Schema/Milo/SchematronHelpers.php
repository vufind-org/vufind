<?php

/**
 * Helpers for work with LibXML and DOM.
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Record
 * @author   Miloslav Hůla <miloslav.hula@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/milo/schematron
 */

namespace Finna\Record\Schema\Milo;

use DOMElement;
use ErrorException;

use function count;
use function func_get_args;

/**
 * Helpers for work with LibXML and DOM.
 *
 * @category VuFind
 * @package  Record
 * @author   Miloslav Hůla <miloslav.hula@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/milo/schematron
 */
class SchematronHelpers
{
    /**
     * XML Errors
     *
     * @var array
     */
    private static $handleXmlErrors = [];

    /**
     * Enable LibXML internal error handling.
     *
     * @param bool $clear Clear existing errors
     *
     * @return void
     */
    public static function handleXmlErrors($clear = true)
    {
        self::$handleXmlErrors[] = libxml_use_internal_errors(true);
        $clear && libxml_clear_errors();
    }

    /**
     * Fetch all LibXML errors.
     *
     * @param bool $restoreHandling Restore LibXML internal error handling?
     *
     * @return ?ErrorException  all errors chained in exceptions
     */
    public static function fetchXmlErrors($restoreHandling = true)
    {
        $e = null;
        foreach (array_reverse(libxml_get_errors()) as $error) {
            $e = new ErrorException(trim($error->message), $error->code, $error->level, $error->file, $error->line, $e);
        }
        libxml_clear_errors();
        $restoreHandling && self::restoreErrorHandling();
        return $e;
    }

    /**
     * Restore LibXML internal error handling previously enabled by self::handleXmlErrors()
     *
     * @return void
     */
    public static function restoreErrorHandling()
    {
        libxml_use_internal_errors(array_pop(self::$handleXmlErrors));
    }

    /**
     * Returns value of element attribute.
     *
     * @param DOMElement $element Element
     * @param string     $name    Attribute name
     *
     * @return mixed
     *
     * @throws SchematronException  when attribute does not exist and default value is not specified
     */
    public static function getAttribute(DOMElement $element, $name)
    {
        if ($element->hasAttribute($name)) {
            return $element->getAttribute($name);
        } elseif (count($args = func_get_args()) > 2) {
            return $args[2];
        }

        throw new SchematronException(
            "Missing required attribute '$name' for element <$element->nodeName> on line {$element->getLineNo()}."
        );
    }
}
