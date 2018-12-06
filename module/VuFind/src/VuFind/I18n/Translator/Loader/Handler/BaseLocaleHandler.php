<?php

namespace VuFind\I18n\Translator\Loader\Handler;

use VuFind\I18n\Translator\Loader\Command\LoadLocaleCommand;

class BaseLocaleHandler implements HandlerInterface
{
    public function __invoke(HandlerContext $context, $command): \Generator
    {
        if (!$command instanceof LoadLocaleCommand) {
            return;
        }

        if ($locale = $this->getBaseLocale($command->getLocale())) {
            yield from $context->run((clone $command)->setLocale($locale));
        }
    }

    protected function getBaseLocale(string $locale)
    {
        $parts = array_slice(array_reverse(explode('-', $locale)), 1);
        return implode('-', array_reverse($parts));
    }
}