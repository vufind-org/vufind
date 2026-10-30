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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  CAPTCHA
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Captcha;

use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\Session\Container;
use Laminas\Session\SessionManager;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;

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
     * Create an object.
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
        ?array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }

        $config = $container->get(\VuFind\Config\ConfigManagerInterface::class)
            ->getConfigArray('config')['Captcha'] ?? [];

        $phraseBuilder = new PhraseBuilder((int)($config['image_length'] ?? 8));
        $captchaBuilder = new CaptchaBuilder(builder: $phraseBuilder);
        $captchaBuilder->setImageType('png');

        if (null !== ($distortion = $config['image_distortion'] ?? null)) {
            $captchaBuilder->setDistortion((bool)$distortion);
        }
        if (null !== ($scatter = $config['image_scatter_effect'] ?? null)) {
            $captchaBuilder->setScatterEffect((bool)$scatter);
        }
        if (null !== ($behind = $config['image_max_behind_lines'] ?? null)) {
            $captchaBuilder->setMaxBehindLines((int)$behind);
        }
        if (null !== ($front = $config['image_max_front_lines'] ?? null)) {
            $captchaBuilder->setMaxFrontLines((int)$front);
        }
        $width = (int)($config['image_width'] ?? 200);
        $height = (int)($config['image_height'] ?? 50);

        return new $requestedName(
            $captchaBuilder,
            new Container('ImageCaptcha', $container->get(SessionManager::class)),
            $width,
            $height
        );
    }
}
