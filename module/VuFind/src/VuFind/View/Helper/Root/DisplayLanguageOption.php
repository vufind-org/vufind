<?php
/**
 * DisplayLanguageOption view helper
 *
 * PHP version 7
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

use Zend\I18n\Translator\TranslatorInterface;
use Zend\Http\PhpEnvironment\Request;

/**
 * DisplayLanguageOption view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class DisplayLanguageOption extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Translator (or null if unavailable)
     *
     * @var TranslatorInterface
     */
    protected $translator = null;

    /**
     * Constructor
     *
     * @param TranslatorInterface $translator Main VuFind translator
     * @param Request             $request    Request object for GET parameters
     */
    public function __construct(TranslatorInterface $translator, Request $request)
    {
        $this->translator = $translator;
        $this->request = $request;
        try {
            $this->translator->addTranslationFile(
                'ExtendedIni', null, 'default', 'native'
            );
            $this->translator->setLocale('native');
        } catch (\Zend\Mvc\I18n\Exception\BadMethodCallException $e) {
            if (!extension_loaded('intl')) {
                throw new \Exception(
                    'Translation broken due to missing PHP intl extension.'
                    . ' Please disable translation or install the extension.'
                );
            }
        }
    }

    /**
     * Translate a string
     *
     * @param string $str String to escape and translate
     *
     * @return string
     */
    public function __invoke($str = null)
    {
        if ($str === null) {
            return $this;
        }
        return $this->view->escapeHtml($this->translator->translate($str));
    }

    /**
     * Get URL to change the language via a GET parameter
     *
     * @param sring          $code Language code
     *
     * @return string
     */
    public function getUrl($code)
    {
        $requestQuery = $this->request->getQuery()->toArray();
        $options = ['query' => array_merge($requestQuery, ['lng' => $code])];
        return $this->view->url(null, [], $options);
    }
}
