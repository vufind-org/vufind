<?php

/**
 * ReCaptcha factory.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  Service
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Service;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use VuFind\Config\Feature\SecretTrait;
use VuFind\I18n\Locale\LocaleSettings;

/**
 * ReCaptcha factory.
 *
 * @category VuFind
 * @package  Service
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ReCaptchaFactory implements FactoryInterface
{
    use SecretTrait;

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
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }

        $config = $container->get(\VuFind\Config\PluginManager::class)
            ->get('config');

        $legacySettingsMap = [
            'publicKey' => 'recaptcha_siteKey',
            'siteKey' => 'recaptcha_siteKey',
            'privateKey' => 'recaptcha_secretKey',
            'secretKey' => 'recaptcha_secretKey',
            'theme' => 'recaptcha_theme',
        ];

        $recaptchaConfig = $config->Captcha->toArray();
        foreach ($legacySettingsMap as $old => $new) {
            if (isset($recaptchaConfig[$old])) {
                error_log(
                    'Deprecated ' . $old . ' setting found in config.ini - '
                    . 'please use ' . $new . ' instead.'
                );
                if (!isset($recaptchaConfig[$new])) {
                    $recaptchaConfig[$new] = $recaptchaConfig[$old];
                }
            }
        }

        $siteKey = $recaptchaConfig['recaptcha_siteKey'] ?? '';
        $secretKey = $this->getSecretFromConfig($recaptchaConfig, 'recaptcha_secretKey') ?? '';
        $httpClient = $container->get(\VuFindHttp\HttpService::class)
            ->createClient();
        $language = $container->get(LocaleSettings::class)->getUserLocale();
        $rcOptions = ['lang' => $language];
        if (isset($recaptchaConfig['recaptcha_theme'])) {
            $rcOptions['theme'] = $recaptchaConfig['recaptcha_theme'];
        }
        return new $requestedName(
            $siteKey,
            $secretKey,
            ['ssl' => true],
            $rcOptions,
            null,
            $httpClient
        );
    }
}
