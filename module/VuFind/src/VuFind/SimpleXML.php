<?php
/**
 * VuFind SimpleXML enhancement functionality
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2009.
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
 * @package  SimpleXML
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind;

/**
 * VuFind SimpleXML enhancement functionality
 *
 * @category VuFind2
 * @package  SimpleXML
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class SimpleXML
{
    /**
     * Attach $child to $parent.  Adapted from function defined in PHP docs here:
     *      http://www.php.net/manual/en/class.simplexmlelement.php#99071
     *
     * @param SimpleXMLElement $parent Parent element to modify
     * @param SimpleXMLElement $child  Child element to attach
     *
     * @return void
     */
    public static function appendElement($parent, $child)
    {
        // get all namespaces for document
        $namespaces = $child->getNamespaces(true);

        // check if there is a default namespace for the current node
        $currentNs = $child->getNamespaces();
        $defaultNs = count($currentNs) > 0 ? current($currentNs) : null;
        $prefix = (count($currentNs) > 0) ? current(array_keys($currentNs)) : '';
        $childName = strlen($prefix) > 1
            ? $prefix . ':' . $child->getName() : $child->getName();

        // check if the value is string value / data
        if (trim((string) $child) == '') {
            $element = $parent->addChild($childName, null, $defaultNs);
        } else {
            $element = $parent->addChild(
                $childName, htmlspecialchars((string)$child), $defaultNs
            );
        }

        foreach ($child->attributes() as $attKey => $attValue) {
            $element->addAttribute($attKey, $attValue);
        }
        foreach ($namespaces as $nskey => $nsurl) {
            foreach ($child->attributes($nsurl) as $attKey => $attValue) {
                $element->addAttribute($nskey . ':' . $attKey, $attValue, $nsurl);
            }
        }

        // add children -- try with namespaces first, but default to all children
        // if no namespaced children are found.
        $children = 0;
        foreach ($namespaces as $nskey => $nsurl) {
            foreach ($child->children($nsurl) as $currChild) {
                self::appendElement($element, $currChild);
                $children++;
            }
        }
        if ($children == 0) {
            foreach ($child->children() as $currChild) {
                self::appendElement($element, $currChild);
            }
        }
    }
}