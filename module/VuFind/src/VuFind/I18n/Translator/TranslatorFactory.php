<?php
/**
 * Translator factory.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2019.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\I18n\Translator;

use Interop\Container\ContainerInterface;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\EventManager\EventInterface;
use Laminas\I18n\Translator\TextDomain;
use Laminas\I18n\Translator\Translator;
use Laminas\ServiceManager\Factory\DelegatorFactoryInterface;
use VuFind\Cache\Manager;
use VuFind\I18n\Locale\LocaleSettings as LocaleSettings;
use VuFind\I18n\Translator\Loader\LoaderInterface;

/**
 * Translator factory.
 *
 * @category VuFind
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
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
        $this->loader = $container->get(LoaderInterface::class);

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

    public function loadMessages(EventInterface $event): TextDomain
    {
        $locale = $event->getParam('locale');
        $textDomain = $event->getParam('text_domain');
        return $this->loader->load($locale, $textDomain);
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
