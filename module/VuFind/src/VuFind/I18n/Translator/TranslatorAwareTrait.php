<?php
/**
 * Lightweight translator aware marker interface.
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
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\I18n\Translator;
use Zend\I18n\Translator\TranslatorInterface;

/**
 * Lightweight translator aware marker interface (used as an alternative to
 * \Zend\I18n\Translator\TranslatorAwareInterface, which requires an excessive
 * number of methods to be implemented).  If we switch to PHP 5.4 traits in the
 * future, we can eliminate this interface in favor of the default Zend version.
 *
 * @category VuFind2
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
trait TranslatorAwareTrait
{
    /**
     * Translator
     *
     * @var \Zend\I18n\Translator\TranslatorInterface
     */
    protected $translator = null;

    /**
     * Set a translator
     *
     * @param TranslatorInterface $translator Translator
     *
     * @return TranslatorAwareInterface
     */
    public function setTranslator(TranslatorInterface $translator)
    {
        $this->translator = $translator;
        return $this;
    }

    /**
     * Get translator object.
     *
     * @return \Zend\I18n\Translator\TranslatorInterface
     */
    public function getTranslator()
    {
        return $this->translator;
    }

    /**
     * Get the locale from the translator.
     *
     * @param string $default Default to use if translator absent.
     *
     * @return string
     */
    public function getTranslatorLocale($default = 'en')
    {
        return null !== $this->translator
            ? $this->translator->getLocale()
            : $default;
    }

    /**
     * Translate a string (or string-castable object)
     *
     * @param string|object $str     String to translate
     * @param array         $tokens  Tokens to inject into the translated string
     * @param string        $default Default value to use if no translation is found
     * (null for no default).
     *
     * @return string
     */
    public function translate($str, $tokens = [], $default = null)
    {
        // Special case: deal with objects with a designated display value:
        if ($str instanceof \VuFind\I18n\TranslatableStringInterface) {
            $translated = $this->translateString((string)$str, $tokens, $default);
            if ($translated !== (string)$str) {
                return $translated;
            }
            return $this->translateString(
                $str->getDisplayString(), $tokens, $default
            );
        }

        // Default case: deal with ordinary strings (or string-castable objects):
        return $this->translateString((string)$str, $tokens, $default);
    }

    /**
     * Get translation for a string
     *
     * @param string $str     String to translate
     * @param array  $tokens  Tokens to inject into the translated string
     * @param string $default Default value to use if no translation is found (null
     * for no default).
     *
     * @return string
     */
    protected function translateString($str, $tokens = [], $default = null)
    {
        $msg = null === $this->translator
            ? $str : $this->translator->translate($str);

        // Did the translation fail to change anything?  If so, use default:
        if (null !== $default && $msg == $str) {
            $msg = $default;
        }

        // Do we need to perform substitutions?
        if (!empty($tokens)) {
            $in = $out = [];
            foreach ($tokens as $key => $value) {
                $in[] = $key;
                $out[] = $value;
            }
            $msg = str_replace($in, $out, $msg);
        }

        return $msg;
    }
}
