<?php

/**
 * DOMXPath envelope.
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

use DOMNode;
use DOMXPath;

/**
 * DOMXPath envelope.
 *
 * @category VuFind
 * @package  Record
 * @author   Miloslav Hůla <miloslav.hula@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/milo/schematron
 */
class SchematronXPath extends DOMXPath
{
    /**
     * Query
     *
     * ($registerNodeNS is FALSE in opposition to DOMXPath default value)
     *
     * @param string   $expression     Expression
     * @param ?DOMNode $context        Context node
     * @param bool     $registerNodeNS Register namespaces?
     *
     * @return mixed
     */
    public function query($expression, ?DOMNode $context = null, $registerNodeNS = false): mixed
    {
        return parent::query($expression, $context, $registerNodeNS);
    }

    /**
     * Evaluate
     *
     * ($registerNodeNS is FALSE in opposition to DOMXPath default value)
     *
     * @param string   $expression     Expression
     * @param ?DOMNode $context        Context node
     * @param bool     $registerNodeNS Register namespaces?
     *
     * @return mixed
     */
    public function evaluate($expression, ?DOMNode $context = null, $registerNodeNS = false): mixed
    {
        return parent::evaluate($expression, $context, $registerNodeNS);
    }

    /**
     * Query context
     *
     * ($registerNodeNS is FALSE in opposition to DOMXPath default value)
     *
     * @param string   $expression     Expression
     * @param ?DOMNode $context        Context node
     * @param bool     $registerNodeNS Register namespaces?
     *
     * @return mixed
     */
    public function queryContext($expression, ?DOMNode $context = null, $registerNodeNS = false): mixed
    {
        if (isset($expression[0]) && $expression[0] !== '.' && $expression[0] !== '/') {
            $expression = "//$expression";
        }
        return $this->query($expression, $context, $registerNodeNS);
    }
}
