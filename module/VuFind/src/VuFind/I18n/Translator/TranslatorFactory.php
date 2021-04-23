<?php
/**
 * Translator factory.
 *
 * PHP version 7
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

use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Laminas\Mvc\I18n\Translator;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use VuFind\I18n\Locale\LocaleSettings;

/**
 * Translator factory.
 *
 * @category VuFind
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class TranslatorFactory extends \Laminas\Mvc\I18n\TranslatorFactory
{
    use \VuFind\I18n\Translator\LanguageInitializerTrait;

    /**
     * Create an object
     *
     * @param ContainerInterface $container     Service manager
     * @param string             $requestedName Service being created
     * @param null|array         $options       Extra options (optional)
     *
     * @return object
     *
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     * creating a service.
     * @throws ContainerException&\Throwable if any other error occurs
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        $translator = parent::__invoke($container, $requestedName, $options);
        if (!extension_loaded('intl')) {
            error_log(
                'Translation broken due to missing PHP intl extension.'
            );
            return $translator;
        }
        $pm = $translator->getPluginManager();
        $settings = $container->get(LocaleSettings::class);
        $language = $settings->getUserLocale();
        $pm->setService('ExtendedIni', $this->getExtendedIni($settings));
        $this->enableCaching($translator, $container);
        $translator->setLocale($language);
        $this->addLanguageToTranslator($translator, $settings, $language);

        return $translator;
    }

    /**
     * Add caching to a translator object
     *
     * @param Translator         $translator Translator object
     * @param ContainerInterface $container  Service manager
     *
     * @return void
     */
    protected function enableCaching(
        Translator $translator,
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
                'Problem loading cache: ' . get_class($e) . ' exception: '
                . $e->getMessage()
            );
        }
    }

    /**
     * Get the ExtendedIni loader.
     *
     * @param LocaleSettings $settings Locale settings object
     *
     * @return Loader\ExtendedIni
     */
    protected function getExtendedIni(LocaleSettings $settings): Loader\ExtendedIni
    {
        $pathStack = [
            APPLICATION_PATH . '/languages',
            LOCAL_OVERRIDE_DIR . '/languages'
        ];
        $fallbackLocales = $settings->getFallbackLocales();
        return new Loader\ExtendedIni($pathStack, $fallbackLocales);
    }
}
