<?php

namespace VuFind\I18n\Translator\Loader\Handler;


use VuFind\I18n\Translator\Loader\Handler\Action\ActionInterface;
use VuFind\I18n\Translator\Loader\Handler\Action\InitialAction;

class InitialHandler implements HandlerInterface
{
    use HandlerTrait;

    public function canHandle(ActionInterface $action): bool
    {
        return $action instanceof InitialAction;
    }

    protected function doHandle(InitialAction $action, HandlerChain $chain): \Generator
    {
        foreach ($chain->next($action) as $file => $data) {
            $data['@meta']['data'] = clone $data;
            $data['@meta']['filename'] = $file;
            $data['@meta']['locale'] = $action->getLocale();
            $data['@meta']['textDomain'] = $action->getTextDomain();
            yield $file => $data;
        }

        if ($locale = $this->getBaseLocale($action->getLocale())) {
            $localeAction = new InitialAction($locale, $action->getTextDomain());
            yield from $chain->head($localeAction);
        }
    }
    
    protected function getBaseLocale(string $locale)
    {
        $parts = array_slice(array_reverse(explode('-', $locale)), 1);
        return implode('-', array_reverse($parts));
    }
}