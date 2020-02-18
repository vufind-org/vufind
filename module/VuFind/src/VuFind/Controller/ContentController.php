<?php
/**
 * Content Controller
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Controller;

/**
 * Controller for mostly static pages that doesn't fall under any particular
 * function.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ContentController extends AbstractBase
{
    /**
     * Default action if none provided
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function contentAction()
    {
        $page = $this->params()->fromRoute('page');
        $themeInfo = $this->serviceLocator->get(\VuFindTheme\ThemeInfo::class);
        $language = $this->serviceLocator->get(\Zend\Mvc\I18n\Translator::class)
            ->getLocale();
        $defaultLanguage = $this->getConfig()->Site->language;

        $types = [
            'phtml',
            'md',
        ];

        // Try to find a template using
        // 1.) Current language suffix
        // 2.) Default language suffix
        // 3.) No language suffix
        $templates = [
            "{$page}_$language",
            "{$page}_$defaultLanguage",
            $page,
        ];

        $pathPrefix = "templates/content/";

        foreach ($templates as $template) {
            foreach ($types as $type) {
                $filename = "$pathPrefix$template.$type";
                $path = $themeInfo->findContainingTheme($filename, true);
                if (null != $path) {
                    $page = $template;
                    $renderer = $type;
                    break 2;
                }
            }
        }

        if ($renderer === 'phtml') {
            return $this->createViewModel(['page' => $page]);
        } elseif ($renderer === 'md') {
            $view = $this->createViewModel();
            $view->setTemplate('content/markdown');
            $view->setVariable('data', file_get_contents($path));
            return $view;
        }

        return $this->notFoundAction($this->getResponse());
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
}
