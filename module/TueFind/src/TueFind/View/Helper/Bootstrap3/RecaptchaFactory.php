<?php

namespace TueFind\View\Helper\Bootstrap3;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class RecaptchaFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }
        return new $requestedName(
            $container->get(\TueFind\Service\ReCaptcha::class),
            $container->get(\VuFind\Config\PluginManager::class)->get('config')
        );
    }
}
