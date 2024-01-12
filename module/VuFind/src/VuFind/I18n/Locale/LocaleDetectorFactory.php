<?php

/**
 * Locale Detector Delegator Factory
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018,
 *               Leipzig University Library <info@ub.uni-leipzig.de> 2018.
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
 * @package  I18n\Locale
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\I18n\Locale;

use Laminas\EventManager\EventInterface;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\DelegatorFactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use SlmLocale\LocaleEvent;
use SlmLocale\Strategy\QueryStrategy;
use VuFind\Cookie\CookieManager;

use function call_user_func;

/**
 * Locale Detector Delegator Factory
 *
 * @category VuFind
 * @package  I18n\Locale
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class LocaleDetectorFactory implements DelegatorFactoryInterface
{
    /**
     * A factory that creates delegates of a given service
     *
     * @param ContainerInterface $container Container
     * @param string             $name      Service name
     * @param callable           $callback  Primary factory
     * @param null|array         $options   Options
     *
     * @return object
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     *     creating a service.
     * @throws ContainerException&\Throwable if any other error occurs
     */
    public function __invoke(
        ContainerInterface $container,
        $name,
        callable $callback,
        array $options = null
    ) {
        $detector = call_user_func($callback);
        $settings = $container->get(LocaleSettings::class);
        $detector->setDefault($settings->getDefaultLocale());
        $detector->setSupported(array_keys($settings->getEnabledLocales()));
        // TODO: implement mappings in the future?
        //$detector->setMappings($settings->getMappedLocales());

        foreach ($this->getStrategies($settings) as $strategy) {
            $detector->addStrategy($strategy);
        }

        $cookies = $container->get(CookieManager::class);
        $detector->getEventManager()->attach(
            LocaleEvent::EVENT_FOUND,
            function (EventInterface $event) use ($cookies) {
                $language = $event->getParam('locale');
                if ($language !== $cookies->get('language')) {
                    $cookies->set('language', $language);
                }
            }
        );

        return $detector;
    }

    /**
     * Generator for retrieving strategies.
     *
     * @param ?LocaleSettings $settings Locale settings
     *
     * @return \Generator
     */
    protected function getStrategies(LocaleSettings $settings = null): \Generator
    {
        yield new LocaleDetectorParamStrategy();

        $queryStrategy = new QueryStrategy();
        $queryStrategy->setOptions(['query_key' => 'lng']);
        yield $queryStrategy;

        $cookieStrategy = new LocaleDetectorCookieStrategy();
        $cookieStrategy->setCookieName('language');
        yield $cookieStrategy;

        // By default, we want to use the HTTP Accept header, so we'll add that
        // strategy when no settings are provided, or when the settings tell us
        // that browser language detection should be used.
        if (!$settings || $settings->browserLanguageDetectionEnabled()) {
            yield new \SlmLocale\Strategy\HttpAcceptLanguageStrategy();
        }
    }
}
