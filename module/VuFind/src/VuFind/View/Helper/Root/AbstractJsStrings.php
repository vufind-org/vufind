<?php

/**
 * AbstractJsStrings helper for passing transformed text to Javascript
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;

/**
 * AbstractJsStrings helper for passing transformed text to Javascript
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractJsStrings extends AbstractHelper
{
    /**
     * Variable name to store values
     *
     * @var string
     */
    protected $varName;

    /**
     * Strings to convey (key = js key, value = value to map)
     *
     * @var array
     */
    protected $strings = [];

    /**
     * Constructor
     *
     * @param string $varName Variable name to store values
     */
    public function __construct($varName = 'vufindString')
    {
        $this->varName = $varName;
    }

    /**
     * Transform strings before JSON encoding
     *
     * @param string|array $str String to transform
     * @param string       $key JSON object key
     *
     * @return string
     */
    abstract protected function mapValue($str, string $key): string;

    /**
     * Add strings to the internal array.
     *
     * @param array $new Strings to add
     *
     * @return void
     */
    public function addStrings($new)
    {
        foreach ($new as $k => $v) {
            $this->strings[$k] = $v;
        }
    }

    /**
     * Generate JSON from an array
     *
     * @param array $strings Strings to convey (key = js key, value = value to map)
     *
     * @return string
     */
    public function getJSONFromArray(array $strings): string
    {
        $ret = [];
        foreach ($strings as $key => $str) {
            $ret[$key] = $this->mapValue($str, $key);
        }
        return json_encode($ret);
    }

    /**
     * Generate JSON from the internal strings
     *
     * @return string
     */
    public function getJSON()
    {
        return $this->getJSONFromArray($this->strings);
    }

    /**
     * Assign JSON to a variable.
     *
     * @return string
     */
    public function getScript()
    {
        return $this->varName . ' = ' . $this->getJSON() . ';';
    }
}
