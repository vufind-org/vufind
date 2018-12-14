<?php

namespace VuFind\I18n\Translator\Loader\Listener;

use VuFind\I18n\Translator\Loader\LoaderTrait;
use Zend\EventManager\EventInterface;

trait ListenerTrait
{
    use LoaderTrait;

    public function __invoke(EventInterface $event): \Generator
    {
        yield from $this->invoke($event);
    }

    abstract protected function invoke(EventInterface $event): \Generator;
}