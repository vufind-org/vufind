<?php
/**
 * Class for translatable string with a special default translation.
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
 * @package  Translator
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\I18n;

/**
 * Class for translatable string with a special default translation.
 *
 * @category VuFind2
 * @package  Translator
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class TranslatableString implements TranslatableStringInterface
{
    /**
     * Original string
     *
     * @var string
     */
    protected $string;

    /**
     * Default display string
     *
     * @var string
     */
    protected $displayString;

    /**
     * Constructor
     *
     * @param string $string        Original string
     * @param string $displayString Translatable display string
     */
    public function __construct($string, $displayString)
    {
        $this->string = (string)$string;
        $this->displayString = $displayString;
    }

    /**
     * Return the original string by default
     *
     * @return string
     */
    public function __toString()
    {
        return $this->string;
    }

    /**
     * Return string for display if raw value has no translation available (can be
     * further translated)
     *
     * @return string
     */
    public function getDisplayString()
    {
        return $this->displayString;
    }
}
