<?php

namespace TueFind\View\Helper\Root;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class HelpTextFactory implements FactoryInterface
{

    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }

        $lang = $container->has('VuFind\Translator')
            ? $container->get('VuFind\Translator')->getLocale()
            : 'en';
        $helpers = $container->get('ViewHelperManager');
        return new HelpText($helpers->get('context'), $lang);
    }
}
