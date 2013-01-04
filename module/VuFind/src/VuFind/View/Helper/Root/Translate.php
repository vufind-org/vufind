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
namespace VuFind\View\Helper\Root;
use Zend\I18n\Exception\RuntimeException,
    Zend\I18n\View\Helper\AbstractTranslatorHelper;

/**
 * Translate view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class Translate extends AbstractTranslatorHelper
{
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
        try {
            $translator = $this->getTranslator();
            if (!is_object($translator)) {
                throw new RuntimeException();
            }
            $msg = $translator->translate($str);
        } catch (RuntimeException $e) {
            // If we get called before the translator is set up, it will throw an
            // exception, but we should still try to display some text!
            $msg = $str;
        }

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
    }
}