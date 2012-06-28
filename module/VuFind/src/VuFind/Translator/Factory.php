<?php
/**
 * VuFind Translate Factory
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
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Translator;
use Zend\Translator\Translator;

/**
 * Factory class to initialize Zend_Translate objects for VuFind.
 *
 * @category VuFind2
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Factory
{
    /**
     * Obtain a translator for the specified language file.
     *
     * @param string $language Language code for translator to support.
     *
     * @return Zend_Translate
     */
    public static function getTranslator($language)
    {
        // Determine the locale -- "native" is special value used for translating
        // languages into their native forms; we use 'en' in this case simply to
        // avoid warnings from Zend_Translate().
        $locale = ($language == 'native') ? 'en' : $language;

        // Set up the translator object:
        $translator = new Translator(
            array(
                'adapter' => 'VuFind\Translator\Adapter\ExtendedIni',
                'content' =>
                    realpath(APPLICATION_PATH . "/languages/{$language}.ini"),
                'locale' => $locale
            )
        );

        // Add local language overrides if they exist:
        $local = LOCAL_OVERRIDE_DIR . "/languages/{$language}.ini";
        if (file_exists($local)) {
            $translator->addTranslation(
                array(
                    'content' => $local,
                    'locale' => $locale
                )
            );
        }

        return $translator;
    }
}
