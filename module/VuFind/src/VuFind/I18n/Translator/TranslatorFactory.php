<?php

namespace VuFind\I18n\Translator;

use Interop\Container\ContainerInterface;
use VuFind\Cache\Manager;
use VuFind\I18n\Locale\LocaleSettings as LocaleSettings;
use VuFind\I18n\Translator\Loader\LoaderInterface;
use VuFind\I18n\Translator\Loader\PluginManager;
use Zend\Cache\Storage\StorageInterface;
use Zend\EventManager\EventInterface;
use Zend\I18n\Translator\TextDomain;
use Zend\I18n\Translator\Translator;
use Zend\ServiceManager\Factory\DelegatorFactoryInterface;

class TranslatorFactory implements DelegatorFactoryInterface
{
    const EVENT_LOAD_MESSAGES = Translator::EVENT_NO_MESSAGES_LOADED;

    const EVENT_LOAD_FALLBACK = Translator::EVENT_MISSING_TRANSLATION;
    /**
     * @var string[][]
     */
    protected $fallbackTraces = [];

    /**
     * @var array
     */
    protected $fallbackLocales;

    /**
     * @var LoaderInterface
     */
    protected $loader;

    /**
     * @var Translator
     */
    protected $translator;

    public function __invoke(ContainerInterface $container, $name, callable $callback, array $options = null)
    {
        /** @var LocaleSettings $localeSettings */
        $localeSettings = $container->get(LocaleSettings::class);
        $this->fallbackLocales = $localeSettings->getFallbackLocales();

        $this->loader = $container->get(PluginManager::class)->get(LoaderInterface::class);

        /** @var Translator $translator */
        $translator = $this->translator = call_user_func($callback);

        /** @var StorageInterface $cache */
        $cache = $container->get(Manager::class)->getCache('language');
        $translator->setCache($cache);

        $translator->enableEventManager();
        $events = $translator->getEventManager();
        $events->attach(static::EVENT_LOAD_MESSAGES, [$this, 'loadMessages']);
        $events->attach(static::EVENT_LOAD_FALLBACK, [$this, 'loadFallback']);

        return $translator;
    }


    public function loadMessages(EventInterface $event)
    {
        $result = new TextDomain();
        $locale = $event->getParam('locale');
        $textDomain = $event->getParam('text_domain');
        $loadedFiles = $this->loader->load("messages://$locale/$textDomain");
        foreach ($loadedFiles as $file => $data) {
            $files[] = $file;
            $store[$file] = $data;
            $result->merge($data);
        }

        if (!$data && $textDomain === 'default') {
            throw new TranslatorRuntimeException("Locale $locale could not be loaded!");
        }

        return $result;
    }

    public function loadFallback(EventInterface $event)
    {
        if (!($locale = $this->fallbackLocales[$event->getParam('locale')] ?? null)) {
            return null;
        }

        $message = $event->getParam('message');
        $textDomain = $event->getParam('text_domain');
        $fallbackTrace = $this->fallbackTraces[$textDomain][$message] ?? [];

        if (in_array($locale, $fallbackTrace)) {
            throw new TranslatorRuntimeException("Circular chain of fallback locales!");
        }

        $count = count($fallbackTrace) + 1;
        $this->fallbackTraces[$textDomain][$message][] = $locale;
        $result = $this->translator->translate($message, $textDomain, $locale);

        if ($count === count($this->fallbackTraces[$textDomain][$message])) {
            $this->fallbackTraces[$textDomain][$message] = [];
        }
        return $result;
    }
}