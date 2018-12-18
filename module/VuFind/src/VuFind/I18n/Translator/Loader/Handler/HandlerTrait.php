<?php

namespace VuFind\I18n\Translator\Loader\Handler;

use VuFind\I18n\Translator\Loader\Handler\Action\ActionInterface;

trait HandlerTrait
{
    public function handle(ActionInterface $action, HandlerChain $chain): \Generator
    {
        yield from $this->canHandle($action) ? $this->doHandle($action, $chain) : $chain->next($action);
    }

    abstract protected function doHandle(ActionInterface $action, HandlerChain $chain): \Generator;
}