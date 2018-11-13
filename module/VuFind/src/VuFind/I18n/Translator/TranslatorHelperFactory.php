<?php

namespace VuFind\I18n\Translator;

use Interop\Container\ContainerInterface;
use VuFind\I18n\Locale\Settings;
use VuFind\I18n\Translator\Reader\PluginManager as Readers;
use Zend\I18n\Translator\TranslatorInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class TranslatorHelperFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        $readers = $container->get(Readers::class);
        $settings = $container->get(Settings::class);
        $translator = $container->get(TranslatorInterface::class);
        return new $requestedName($readers, $settings, $translator);
    }
}