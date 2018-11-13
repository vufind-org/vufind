<?php

namespace VuFind\I18n\Locale;

use Interop\Container\ContainerInterface;
use SlmLocale\Locale\Detector;
use SlmLocale\LocaleEvent;
use SlmLocale\Strategy\CookieStrategy;
use SlmLocale\Strategy\QueryStrategy;
use VuFind\Cookie\CookieManager;
use Zend\EventManager\EventInterface;
use Zend\ServiceManager\Factory\DelegatorFactoryInterface;

class DetectorFactory implements DelegatorFactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        $name,
        callable $callback,
        array $options = null
    ) {
        /** @var Detector $detector */
        $detector = call_user_func($callback);
        /** @var Settings $settings */
        $settings = $container->get(Settings::class);
        $detector->setDefault($settings->getDefaultLocale());
        $detector->setSupported($settings->getEnabledLocales());
        $detector->setMappings($settings->getMappedLocales());

        foreach ($this->getStrategies() as $strategy) {
            $detector->addStrategy($strategy);
        }

        /** @var CookieManager $cookies */
        $cookies = $container->get(CookieManager::class);
        $detector->getEventManager()->attach(LocaleEvent::EVENT_FOUND,
            function (EventInterface $event) use ($cookies) {
                $cookies->set('language', $event->getParam('locale'));
            });

        return $detector;
    }

    protected function getStrategies()
    {
        yield new ParamStrategy();
        yield $queryStrategy = new QueryStrategy();
        yield $cookieStrategy = new CookieStrategy();
        $queryStrategy->setOptions(['query_key' => 'lng']);
        $cookieStrategy->setCookieName('language');
    }
}