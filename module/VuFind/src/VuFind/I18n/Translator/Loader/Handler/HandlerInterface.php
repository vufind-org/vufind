<?php
namespace VuFind\I18n\Translator\Loader\Handler;

use VuFind\I18n\Translator\Loader\Handler\Action\ActionInterface;

interface HandlerInterface
{
    public function handle(ActionInterface $action, HandlerChain $chain): \Generator;

    public function canHandle(ActionInterface $action): bool;
}