<?php
/**
 * ReCaptcha factory.
 *
 * PHP version 7
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

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

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
     * @throws ContainerException if any other error occurs
     */
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }
        $config = $container->get(\VuFind\Config\PluginManager::class)
            ->get('config');
        $siteKey = isset($config->Captcha->siteKey)
            ? $config->Captcha->siteKey
            : (isset($config->Captcha->publicKey)
                ? $config->Captcha->publicKey
                : '');
        $secretKey = isset($config->Captcha->secretKey)
            ? $config->Captcha->secretKey
            : (isset($config->Captcha->privateKey)
                ? $config->Captcha->privateKey
                : '');
        $httpClient = $container->get(\VuFindHttp\HttpService::class)
            ->createClient();
        $translator = $container->get(\Zend\Mvc\I18n\Translator::class);
        $rcOptions = ['lang' => $translator->getLocale()];
        if (isset($config->Captcha->theme)) {
            $rcOptions['theme'] = $config->Captcha->theme;
        }
        return new $requestedName(
            $siteKey, $secretKey, ['ssl' => true], $rcOptions, null, $httpClient
        );
    }
}
