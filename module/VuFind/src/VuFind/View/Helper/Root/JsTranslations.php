<?php
/**
 * JsTranslations helper for passing translation text to Javascript
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\View\Helper\Root;
use Zend\View\Helper\AbstractHelper;

/**
 * JsTranslations helper for passing translation text to Javascript
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class JsTranslations extends AbstractHelper
{
    /**
     * Translate + escape helper
     *
     * @var TransEsc
     */
    protected $transEsc;

    /**
     * Variable name to store translations
     *
     * @var string
     */
    protected $varName;

    /**
     * Strings to translate (key = js key, value = string to translate)
     *
     * @var array
     */
    protected $strings = [];

    /**
     * Constructor
     *
     * @param TransEsc $transEsc Translate + escape helper
     * @param string   $varName  Variable name to store translations
     */
    public function __construct(TransEsc $transEsc, $varName = 'vufindString')
    {
        $this->transEsc = $transEsc;
        $this->varName = $varName;
    }

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
     * Generate Javascript from the internal strings.
     *
     * @return string
     */
    public function getScript()
    {
        $parts = [];
        foreach ($this->strings as $k => $v) {
            $translation = is_array($v)
                ? call_user_func_array([$this->transEsc, '__invoke'], $v)
                : $this->transEsc->__invoke($v);
            $parts[] = $k . ': "' . addslashes($translation) . '"';
        }
        return $this->varName . ' = {' . implode(',', $parts) . '};';
    }
}
