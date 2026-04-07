<?php

/**
 * DisplayLanguageOption view helper.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\Translator\TranslatorInterface;
use Laminas\View\Helper\EscapeHtml;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * DisplayLanguageOption view helper.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class DisplayLanguageOption extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Constructor.
     *
     * @param TranslatorInterface $translator Translator
     * @param EscapeHtml          $escapeHtml EscapeHtml view helper
     */
    public function __construct(
        protected TranslatorInterface $translator,
        #[Autowire(container: 'ViewHelperManager')]
        protected EscapeHtml $escapeHtml
    ) {
    }

    /**
     * Translate a string.
     *
     * @param string $str String to escape and translate
     *
     * @return string
     */
    public function __invoke($str)
    {
        return ($this->escapeHtml)(
            $this->translator->translate($str, 'default', 'native')
        );
    }
}
