<?php

namespace VuFind\I18n\Translator\Loader\Listener;

use Zend\EventManager\EventInterface;
use Zend\EventManager\EventManagerAwareInterface;

interface ListenerInterface extends EventManagerAwareInterface
{
    public function __invoke(EventInterface $event): \Generator;

    public function getEventName(): string;
}