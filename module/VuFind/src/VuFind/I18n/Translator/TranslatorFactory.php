<?php

namespace VuFind\I18n\Translator;

use Interop\Container\ContainerInterface;
use VuFind\Cache\Manager;
use Zend\I18n\Translator\Translator;
use Zend\ServiceManager\Factory\DelegatorFactoryInterface;

class TranslatorFactory implements DelegatorFactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        $name,
        callable $callback,
        array $options = null
    ) {
        /** @var Translator $translator */
        $translator = call_user_func($callback);
        $cache = $container->get(Manager::class)->getCache('language');
        $translator->enableEventManager();
        $translator->setCache($cache);
        return $translator;
    }
}