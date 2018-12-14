<?php

namespace VuFind\I18n\Translator\Loader\Listener;

use VuFind\I18n\Translator\Loader\Event\ExtensionEvent;
use VuFind\I18n\Translator\Loader\Event\FileEvent;
use VuFind\I18n\Translator\TranslatorRuntimeException;
use Zend\Uri\Uri;

class ExtensionListener implements ListenerInterface
{
    use ListenerTrait;

    public function getEventName(): string
    {
        return ExtensionEvent::class;
    }

    protected function invoke(ExtensionEvent $event): \Generator
    {
        $extendingUri = new Uri($extendingFile = $event->getExtendingFile());
        foreach ($event->getExtendedFiles() as $extendedFile) {
            $extendedUri = Uri::merge($extendingUri, $extendedFile);
            yield from $this->handleExtension($extendingFile, (string) $extendedUri);
        }
    }

    protected function handleExtension(string $extendingFile, string $extendedFile): \Generator
    {
        $extendedFiles = $this->trigger(new FileEvent($extendedFile));
        foreach ($extendedFiles as $transitivelyExtendedFile => $data) {
            if ($extendingFile === $transitivelyExtendedFile) {
                throw new TranslatorRuntimeException("Circular chain of language files at $extendingFile");
            }
            yield $transitivelyExtendedFile => $data;
        }
    }
}