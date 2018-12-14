<?php

namespace VuFind\I18n\Translator\Loader\Listener;

use VuFind\I18n\Translator\Loader\Event\InitialEvent;

class BaseLocaleListener implements ListenerInterface
{
    use ListenerTrait;

    public function getEventName(): string
    {
        return InitialEvent::class;
    }

    protected function invoke(InitialEvent $event)
    {
        if ($locale = $this->getBaseLocale($event->getLocale())) {
            $subevent = new InitialEvent($locale, $event->getTextDomain());
            yield from $this->trigger($subevent);
        }
    }

    protected function getBaseLocale(string $locale)
    {
        $parts = array_slice(array_reverse(explode('-', $locale)), 1);
        return implode('-', array_reverse($parts));
    }
}