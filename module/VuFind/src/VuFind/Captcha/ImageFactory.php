<?php

/**
 * Factory for Image CAPTCHA module.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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
 * @package  CAPTCHA
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Captcha;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;

use function is_callable;

/**
 * Image CAPTCHA factory.
 *
 * @category VuFind
 * @package  CAPTCHA
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ImageFactory implements FactoryInterface
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

        $cacheManager = $container->get(\VuFind\Cache\Manager::class);
        $cacheOptions = $cacheManager->getCache('public')->getOptions();
        if (!is_callable([$cacheOptions, 'getCacheDir'])) {
            throw new \Exception('Image CAPTCHA requires access to public cache; is cache disabled?');
        }
        $imageOptions = [
            'font' => APPLICATION_PATH
                    . '/vendor/webfontkit/open-sans/fonts/opensans-regular.ttf',
            'imgDir' => $cacheOptions->getCacheDir(),
        ];

        $config = $container->get(\VuFind\Config\PluginManager::class)
            ->get('config');

        if (isset($config->Captcha->image_length)) {
            $imageOptions['wordLen'] = $config->Captcha->image_length;
        }
        if (isset($config->Captcha->image_width)) {
            $imageOptions['width'] = $config->Captcha->image_width;
        }
        if (isset($config->Captcha->image_height)) {
            $imageOptions['height'] = $config->Captcha->image_height;
        }
        if (isset($config->Captcha->image_fontSize)) {
            $imageOptions['fsize'] = $config->Captcha->image_fontSize;
        }
        if (isset($config->Captcha->image_dotNoiseLevel)) {
            $imageOptions['dotNoiseLevel'] = $config->Captcha->image_dotNoiseLevel;
        }
        if (isset($config->Captcha->image_lineNoiseLevel)) {
            $imageOptions['lineNoiseLevel'] = $config->Captcha->image_lineNoiseLevel;
        }

        $baseUrl = rtrim(
            ($container->get('ViewHelperManager')->get('url'))('home') ?? '',
            '/'
        );
        return new $requestedName(
            new \Laminas\Captcha\Image($imageOptions),
            "$baseUrl/cache/"
        );
    }
}
