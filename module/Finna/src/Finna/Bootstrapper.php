<?php
/**
 * VuFind Bootstrapper
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @package  Bootstrap
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna;
use Zend\Console\Console, Zend\Mvc\MvcEvent, Zend\Mvc\Router\Http\RouteMatch;

/**
 * VuFind Bootstrapper
 *
 * @category VuFind2
 * @package  Bootstrap
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Bootstrapper extends \VuFind\Bootstrapper
{
    /**
     * Set up configuration manager.
     *
     * @return void
     */
    protected function initConfig()
    {
        // This is needed for initConfig to be called before
        // overridden initLanguage and initTheme
        parent::initConfig();
    }

    /**
     * Set up language handling.
     *
     * @return void
     */
    protected function initLanguage()
    {
        $config = & $this->config;
        $browserCallback = [$this, 'detectBrowserLanguage'];
        $callback = function ($event) use ($config, $browserCallback) {
            $sm = $event->getApplication()->getServiceManager();

            if (!Console::isConsole()) {
                $validBrowserLanguage = call_user_func($browserCallback);

                // Setup Translator
                $request = $event->getRequest();
                $sm = $event->getApplication()->getServiceManager();
                if (($language = $request->getPost()->get('mylang', false))
                    || ($language = $request->getQuery()->get('lng', false))
                ) {
                    $cookieManager = $sm->get('VuFind\CookieManager');
                    $cookieManager->set('language', $language);
                } elseif (!empty($request->getCookie()->language)) {
                    $language = $request->getCookie()->language;
                } else {
                    $language = (false !== $validBrowserLanguage)
                        ? $validBrowserLanguage : $config->Site->language;
                }
                // Make sure language code is valid, reset to default if bad:
                if (!in_array(
                    $language, array_keys($config->Languages->toArray())
                )) {
                    $language = $config->Site->language;
                }
            } else {
                $language = $config->Site->language;
            }

            try {
                $sm->get('VuFind\Translator')
                    ->addTranslationFile('ExtendedIni', null, 'default', $language)
                    ->setLocale($language);
            } catch (\Zend\Mvc\Exception\BadMethodCallException $e) {
                if (!extension_loaded('intl')) {
                    throw new \Exception(
                        'Translation broken due to missing PHP intl extension.'
                        . ' Please disable translation or install the extension.'
                    );
                }
            }
            // Send key values to view:
            $viewModel = $sm->get('viewmanager')->getViewModel();
            $viewModel->setVariable('userLang', $language);
            $viewModel->setVariable('allLangs', $config->Languages);
        };
        $this->events->attach('dispatch.error', $callback, 10000);
        $this->events->attach('dispatch', $callback, 10000);
    }

    /**
     * Set up theme handling.
     *
     * @return void
     */
    protected function initTheme()
    {
        if (!Console::isConsole()) {
            // Attach template injection configuration to the route event:
            $this->events->attach(
                'route', ['FinnaTheme\Initializer', 'configureTemplateInjection']
            );
        }

        // Attach remaining theme configuration to the dispatch event at high
        // priority (TODO: use priority constant once defined by framework):
        $config = $this->config->Site;
        $callback = function ($event) use ($config) {
            $theme = new \FinnaTheme\Initializer($config, $event);
            $theme->init();
        };
        $this->events->attach('dispatch.error', $callback, 9000);
        $this->events->attach('dispatch', $callback, 9000);
    }
}
