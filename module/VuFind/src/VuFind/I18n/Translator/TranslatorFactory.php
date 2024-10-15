<?php

/**
 * Translator factory.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\I18n\Translator;

use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\DelegatorFactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use VuFind\Config\PathResolver;
use VuFind\I18n\Locale\LocaleSettings;

use function extension_loaded;

/**
 * Translator factory.
 *
 * @category VuFind
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class TranslatorFactory implements DelegatorFactoryInterface
{
    use \VuFind\I18n\Translator\LanguageInitializerTrait;

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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(
        ContainerInterface $container,
        $name,
        callable $callback,
        array $options = null
    ) {
        $this->setPathResolver($container->get(PathResolver::class));
        $translator = $callback();
        if (!extension_loaded('intl')) {
            error_log(
                'Translation broken due to missing PHP intl extension.'
            );
            return $translator;
        }
        $settings = $container->get(LocaleSettings::class);
        $language = $settings->getUserLocale();
        $this->enableCaching($translator, $container);
        $this->addLanguageToTranslator($translator, $settings, $language);

        return $translator;
    }

    /**
     * Add caching to a translator object
     *
     * @param TranslatorInterface $translator Translator object
     * @param ContainerInterface  $container  Service manager
     *
     * @return void
     */
    protected function enableCaching(
        TranslatorInterface $translator,
        ContainerInterface $container
    ): void {
        // Set up language caching for better performance:
        try {
            $translator->setCache(
                $container->get(\VuFind\Cache\Manager::class)->getCache('language')
            );
        } catch (\Exception $e) {
            // Don't let a cache failure kill the whole application, but make
            // note of it:
            $logger = $container->get(\VuFind\Log\Logger::class);
            $logger->debug(
                'Problem loading cache: ' . $e::class . ' exception: '
                . $e->getMessage()
            );
        }
    }
}
