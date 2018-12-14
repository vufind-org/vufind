<?php

namespace VuFind\I18n\Translator\Loader\Listener;

use VuFind\I18n\Translator\Loader\Event\FileEvent;
use VuFind\I18n\Translator\Loader\Event\InitialEvent;
use Zend\Stdlib\Glob;

class DirectoryListener implements ListenerInterface
{
    use ListenerTrait;

    /**
     * @var string
     */
    protected $directory;

    /**
     * @var string
     */
    protected $extension;

    public function __construct(array $options)
    {
        $this->directory = $options['dir'];
        $this->extension = $options['ext'];
    }

    public function getEventName(): string
    {
        return InitialEvent::class;
    }

    protected function invoke(InitialEvent $event): \Generator
    {
        $directory = ($textDomain = $event->getTextDomain()) === 'default'
            ? $this->directory : "$this->directory/$textDomain";
        $globPattern = "$directory/{$event->getLocale()}.{{$this->extension}}";
        foreach (Glob::glob($globPattern, Glob::GLOB_BRACE) as $file) {
            yield from $this->trigger(new FileEvent($file));
        }
    }
}