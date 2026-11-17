<?php

/**
 * DevTools language action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
 * Copyright (C) The National Library of Finland 2026.
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
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindDevTools\Action\DevTools;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\I18n\Locale\LocaleSettings;
use VuFind\I18n\Translator\Loader\ExtendedIni;
use VuFind\ServiceManager\Factory\Autowire;
use VuFindDevTools\LanguageHelper;

/**
 * DevTools language action.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class LanguageAction extends AbstractTemplateRenderingAction
{
    /**
     * Constructor.
     *
     * @param LocaleSettings $localeSettings Locale settings
     */
    #[Autowire]
    public function __construct(
        protected LocaleSettings $localeSettings,
    ) {
        parent::__construct();
    }

    /**
     * Display language information.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        // Test languages with no local overrides and no fallback:
        $loader = new ExtendedIni([APPLICATION_PATH . '/languages']);
        $langs = $this->localeSettings->getEnabledLocales();
        $helper = new LanguageHelper($loader, $langs);
        $templateParams = $helper->getAllDetails(
            $this->getQueryParam('main', 'en'),
            (bool)$this->getQueryParam('includeOptional', 1)
        );
        return $this->renderTemplate($request, $response, $templateParams);
    }
}
