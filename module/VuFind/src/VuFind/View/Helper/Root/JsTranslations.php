<?php

/**
 * JsTranslations helper for passing translation text to Javascript
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

/**
 * JsTranslations helper for passing translation text to Javascript
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class JsTranslations extends AbstractJsStrings
{
    /**
     * Translate helper
     *
     * @var Translate
     */
    protected $translate;

    /**
     * Translate + escape helper
     *
     * @var TransEsc
     */
    protected $transEsc;

    /**
     * Constructor
     *
     * @param Translate $translate Translate helper
     * @param TransEsc  $transEsc  Translate + escape helper
     * @param string    $varName   Variable name to store translations
     */
    public function __construct(
        Translate $translate,
        TransEsc $transEsc,
        $varName = 'vufindString'
    ) {
        parent::__construct($varName);
        $this->translate = $translate;
        $this->transEsc = $transEsc;
    }

    /**
     * Translate string
     *
     * @param string|array $translation String to translate
     * @param string       $key         JSON object key
     *
     * @return string
     */
    protected function mapValue($translation, string $key): string
    {
        $translateFunc
            = str_ends_with($key, '_html') || str_ends_with($key, '_unescaped')
            ? $this->translate : $this->transEsc;

        // $translation could be a string or an array of parameters; this code
        // normalizes it into a parameter list for the translator.
        return ($translateFunc)(...((array)$translation));
    }
}
