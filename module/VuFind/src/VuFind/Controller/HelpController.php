<?php
/**
 * Home action for Help module
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
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
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Controller;
use VuFind\Config\Reader as ConfigReader, VuFind\Translator\Translator;

/**
 * Home action for Help module
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class HelpController extends AbstractBase
{
    /**
     * Uses the user language to determine which Help template to use
     * Uses the English template as a back-up
     *
     * @return void  Sends data to the view (tpl_lang, tpl_en)
     */
    public function homeAction()
    {
        $config = ConfigReader::getConfig();
        $this->layout()->setTemplate('layout/help');

        // Sanitize the topic name to include only alphanumeric characters
        // or underscores.
        $safe_topic
            = preg_replace('/[^\w]/', '', $this->params()->fromQuery('topic'));

        // Construct two possible template names -- the help screen in the
        // current selected language and help in English (most likely to exist).
        // The code will attempt to display the most appropriate existing help screen
        $translator = Translator::getTranslator();
        $language = is_object($translator) ? $translator->getLocale() : 'en';

        $tpl_lang = 'HelpTranslations/' . $language
            . '/' . $safe_topic . '.phtml';
        $tpl_en = 'HelpTranslations/en/' . $safe_topic . '.phtml';
       
        // Best case -- help is available in the user's chosen language
        return $this->createViewModel(
            array('tpl_lang' => $tpl_lang, 'tpl_en' => $tpl_en)
        );
    }
}