<?php
/**
 * Translate view helper
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
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
namespace VuFind\Theme\Root\Helper;
use Zend\View\Helper\AbstractHelper;

/**
 * Translate view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class Translate extends AbstractHelper
{
    protected $translator;

    /**
     * Constructor for manually handling
     *
     * @param Zend_Translate|Zend_Translate_Adapter $translate Instance of
     * Zend_Translate
     */
    public function __construct($translate = null)
    {
        /* TODO
        // We can't extend Zend_View_Helper_Translate since we want to change the
        // signature of the translate() method, so instead we will encapsulate an
        // instance:
        $this->translator = new Zend_View_Helper_Translate($translate);
         */
    }

    /**
     * Translate a string
     *
     * @param string $str     String to translate
     * @param array  $tokens  Tokens to inject into the translated string
     * @param string $default Default value to use if no translation is found (null
     * for no default).
     *
     * @return string
     */
    public function __invoke($str, $tokens = array(), $default = null)
    {
        /* TODO:
        $msg = $this->translator->translate($str);

        // Did the translation fail to change anything?  If so, use default:
        if (!is_null($default) && $msg == $str) {
            $msg = $default;
        }

        // Do we need to perform substitutions?
        if (!empty($tokens)) {
            $in = $out = array();
            foreach ($tokens as $key => $value) {
                $in[] = $key;
                $out[] = $value;
            }
            $msg = str_replace($in, $out, $msg);
        }

        return $msg;
         */
        return $str;
    }
}