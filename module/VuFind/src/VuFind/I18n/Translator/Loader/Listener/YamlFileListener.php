<?php

namespace VuFind\I18n\Translator\Loader\Listener;

use Symfony\Component\Yaml\Yaml as Parser;
use VuFind\I18n\Translator\Loader\Event\ExtensionEvent;
use VuFind\I18n\Translator\Loader\Event\FileEvent;
use Zend\I18n\Translator\TextDomain;

class YamlFileListener implements ListenerInterface
{
    use ListenerTrait;

    public function getEventName(): string
    {
        return FileEvent::class;
    }

    protected function invoke(FileEvent $event): \Generator
    {
        if (!$this->canLoad($file = $event->getFile())) {
            return;
        }

        yield $file => $data = new TextDomain(Parser::parseFile($file) ?? []);

        $extendedFiles = (array)($data['@extends'] ?? []);
        yield from $this->trigger(new ExtensionEvent($file, $extendedFiles));
    }

    protected function canLoad(string $file): bool
    {
        return in_array(pathinfo($file, PATHINFO_EXTENSION), ['yml', 'yaml']);
    }
}