<?php

namespace VuFind\I18n\Translator;

use VuFind\I18n\Locale\Settings;
use VuFind\I18n\Translator\Reader\PluginManager as Readers;
use VuFind\I18n\Translator\Resolver\ResolverInterface;
use Zend\EventManager\EventInterface;
use Zend\I18n\Translator\TextDomain;
use Zend\I18n\Translator\Translator;

class TranslatorHelper
{
    const EVENT_RESOLVE_SOURCES = 'resolveSources';

    const KEY_EXTENDS = '@extends';

    const KEY_SOURCES = '@sources';

    const KEY_FALLBACK = '@fallback';

    /**
     * @var TextDomain[]
     */
    protected $cache = [];

    /**
     * @var string[][]
     */
    protected $fallbacks = [];

    /**
     * @var Readers;
     */
    protected $readers;

    /**
     * @var Settings
     */
    protected $settings;

    /**
     * @var Translator
     */
    protected $translator;

    public function __construct(
        Readers $readers,
        Settings $settings,
        Translator $translator
    ) {
        $this->readers = $readers;
        $this->settings = $settings;
        $this->translator = $translator;
        $events = $translator->getEventManager();
        $events->attach(Translator::EVENT_NO_MESSAGES_LOADED, [$this, 'loadMessages']);
        $events->attach(Translator::EVENT_MISSING_TRANSLATION, [$this, 'loadFallbackMessage']);
    }

    public function addResolver(ResolverInterface $resolver, int $priority = 1)
    {
        $this->translator->getEventManager()->attach(self::EVENT_RESOLVE_SOURCES,
            function (EventInterface $event) use ($resolver) {
                $locale = $event->getParam('locale');
                $textDomain = $event->getParam('text_domain');
                return $resolver->resolve($locale, $textDomain);
            }, $priority);
    }

    public function loadMessages(EventInterface $event)
    {
        $locale = $event->getParam('locale');
        $textDomain = $event->getParam('text_domain');
        list ($result, $sources) = [new TextDomain(), []];
        $load = $this->loadLocale($locale, $textDomain);

        foreach ($load as $source => $data) {
            $sources[$source] = $data;
            $result = $result->merge($data);
        }

        if (!$load->getReturn() && $textDomain === 'default') {
            throw new TranslatorException("Locale $locale could not be loaded!");
        }

        $result[self::KEY_SOURCES] = $sources;

        return $result;
    }

    public function loadFallbackMessage(EventInterface $event)
    {
        $locale = $event->getParam('locale');
        $message = $event->getParam('message');
        $textDomain = $event->getParam('text_domain');
        $namespacedMessage = "$textDomain::$message";
        $trace = $this->fallbacks[$namespacedMessage] ?? [];
        $fallbackLocales = $this->settings->getFallbackLocales();

        if (in_array($fallbackLocale = $fallbackLocales[$locale] ?? null, $trace)) {
            throw new TranslatorException("Circular chain of fallback locales!");
        }

        if (!$trace || $locale !== end($trace)) {
            $this->fallbacks[$namespacedMessage] = [];
        }

        if ($fallbackLocale) {
            $this->fallbacks[$namespacedMessage][] = $fallbackLocale;
            return $this->translator->translate($message, $textDomain, $fallbackLocale);
        }
    }

    protected function loadLocale(string $locale, string $textDomain)
    {
        if ($baseLocale = $this->getBaseLocale($locale)) {
            yield from $this->loadLocale($baseLocale, $textDomain);
        }

        $loaded = false;
        $payload = ['locale' => $locale, 'text_domain' => $textDomain];
        $sources = $this->translator->getEventManager()
            ->trigger(self::EVENT_RESOLVE_SOURCES, null, $payload);
        foreach ($sources as $source) {
            if ($source) {
                yield from $this->loadSource($source);
                $loaded = true;
            }
        }
        return $loaded;
    }

    protected function loadSource(string $source, string ...$sources)
    {
        if (key_exists($source = realpath($source), $sources)) {
            throw new TranslatorException("Circular chain of sources!");
        }

        $type = pathinfo($source, PATHINFO_EXTENSION);
        $data = $this->cache[$source] = $this->cache[$source]
            ?? $this->readers->get($type)->read($source);

        if ($parent = $data[self::KEY_EXTENDS] ?? null) {
            $parentSource = $parent[0] === '/' ? $parent
                : pathinfo($source, PATHINFO_DIRNAME) . "/$parent";
            yield from $this->loadSource($parentSource, $source, ...$sources);
        }

        yield ($source) => $this->cache[$source];
    }

    protected function getBaseLocale($locale)
    {
        $parts = array_slice(array_reverse(explode('-', $locale)), 1);
        return implode('-', array_reverse($parts));
    }
}