<?php

namespace VuFind\Controller\Plugin;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CaptchaFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }
        
        $config = $container->get(\VuFind\Config\PluginManager::class)->get('config');
        $captcha = isset($config->Captcha->type) ? $container->get(\VuFind\Captcha\PluginManager::class)->get($config->Captcha->type) : null;
        
        return new $requestedName(
            $captcha,
            $config
        );
    }
}
