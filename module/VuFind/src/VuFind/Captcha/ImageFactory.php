<?php

namespace VuFind\Captcha;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ImageFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }
        
        $imageOptions = [
            'font' => getenv('VUFIND_HOME') . '/vendor/endroid/qr-code/assets/fonts/open_sans.ttf',
            'imgDir' => getenv('VUFIND_LOCAL_DIR') . '/cache/public'
        ];
        
        $config = $container->get(\VuFind\Config\PluginManager::class)
            ->get('config');

        if (isset($config->Captcha->image_length))
            $imageOptions['wordLen'] = $config->Captcha->image_length;
        if (isset($config->Captcha->image_width))
            $imageOptions['width'] = $config->Captcha->image_width;
        if (isset($config->Captcha->image_height))
            $imageOptions['height'] = $config->Captcha->image_height;
        if (isset($config->Captcha->image_fontSize))
            $imageOptions['fsize'] = $config->Captcha->image_fontSize;
        if (isset($config->Captcha->image_dotNoiseLevel))
            $imageOptions['dotNoiseLevel'] = $config->Captcha->image_dotNoiseLevel;
        if (isset($config->Captcha->image_lineNoiseLevel))
            $imageOptions['lineNoiseLevel'] = $config->Captcha->image_lineNoiseLevel;
        
        return new $requestedName(
            new \Laminas\Captcha\Image($imageOptions)
        );
    }
}
