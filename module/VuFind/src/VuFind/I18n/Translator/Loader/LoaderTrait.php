<?php

namespace VuFind\I18n\Translator\Loader;

use Zend\EventManager\EventInterface;
use Zend\EventManager\EventManagerAwareTrait;

trait LoaderTrait
{
    use EventManagerAwareTrait;

    protected function trigger(EventInterface $event): \Generator
    {
        foreach ($this->events->triggerEvent($event) as $results) {
            yield from $results;
        }
    }
}