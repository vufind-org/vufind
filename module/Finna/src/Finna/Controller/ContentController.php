<?php
/**
 * Content Controller
 *
 * PHP Version 5
 *
 * Copyright (C) Villanova University 2011.
 * Copyright (C) The National Library of Finland 2014-2016.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA    02111-1307    USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace Finna\Controller;

/**
 * Loads content pages
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class ContentController extends \VuFind\Controller\AbstractBase
{
    /**
     * Default action if none provided
     *
     * @return Zend\View\Model\ViewModel
     */
    public function contentAction()
    {
        $event      = $this->getEvent();
        $routeMatch = $event->getRouteMatch();
        $page       = strtolower($routeMatch->getParam('page'));
        $themeInfo  = $this->getServiceLocator()->get('VuFindTheme\ThemeInfo');
        $translator = $this->getServiceLocator()->get('VuFind\Translator');
        $language   = $translator->getLocale();
        $action     = "{$page}Action";
        $defaultLanguage = $this->getConfig()->Site->language;

        // Try template with current language first and default language as a
        // fallback
        if (null !==
            $themeInfo->findContainingTheme(
                "templates/content/{$page}_$language.phtml"
            )
        ) {
            $page = "{$page}_$language";
        } elseif (null !==
            $themeInfo->findContainingTheme(
                "templates/content/{$page}_$defaultLanguage.phtml"
            )
        ) {
            $page = "{$page}_$defaultLanguage";
        }

        if (empty($page) || null === $themeInfo->findContainingTheme(
            "templates/content/$page.phtml"
        )) {
            return $this->notFoundAction($this->getResponse());
        }

        $view = $this->createViewModel(['page' => $page]);
        if (method_exists($this, $action)) {
            $view = call_user_func([$this, $action], $view);
        }
        return $view;
    }

    /**
     * Action called if matched action does not exist
     *
     * @return array
     */
    public function notFoundAction()
    {
        $response   = $this->response;

        if ($response instanceof \Zend\Http\Response) {
            return $this->createHttpNotFoundModel($response);
        }
        return $this->createConsoleNotFoundModel($response);
    }

    /**
     * Inject list of login drivers to About Finna page.
     *
     * @param Zend\View\Model\ViewModel $view View
     *
     * @return Zend\View\Model\ViewModel
     */
// @codingStandardsIgnoreStart - method name not in camelCase
    public function about_finnaAction($view)
    {
// @codingStandardsIgnoreEnd - method name not in camelCase
        $catalog = $this->getILS();
        if ($catalog->checkCapability('getLoginDrivers')) {
            $view->loginTargets = $catalog->getLoginDrivers();
        }
        return $view;
    }
}
