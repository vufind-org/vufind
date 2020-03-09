<?php

namespace VuFind\Captcha;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class FigletFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }
        
        $figletOptions = [
            'name' => 'figlet_captcha',
        ];
        
        $config = $container->get(\VuFind\Config\PluginManager::class)
            ->get('config');
        
        if (isset($config->Captcha->figlet_length))
            $figletOptions['wordLen'] = $config->Captcha->figlet_length;
        
        return new $requestedName(
            new \Laminas\Captcha\Figlet($figletOptions)
        );
    }
}
