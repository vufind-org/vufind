<?php

namespace TueFind\Captcha;

use Interop\Container\ContainerInterface;

class ImageFactory extends \VuFind\Captcha\ImageFactory
{
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }

        $imageOptions = [
            'font' => APPLICATION_PATH
                    . '/vendor/webfontkit/open-sans/fonts/opensans-regular.ttf',
            'imgDir' => $container->get(\VuFind\Cache\Manager::class)
                ->getCache('public')->getOptions()->getCacheDir()
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

        // TueFind: Append subdir to url to match apache configuration
        return new $requestedName(
            new \Laminas\Captcha\Image($imageOptions),
            $container->get('ViewHelperManager')->get('url')->__invoke('home')
                . 'cache/'
        );
    }
}
