<?php

namespace VuFind\I18n\Translator\Loader\Handler;

use VuFind\I18n\Translator\Loader\Command\LoadFileCommand;
use VuFind\I18n\Translator\Loader\Command\LoadLocaleCommand;
use Zend\Stdlib\Glob;

class DirectoryHandler implements HandlerInterface
{
    public function __invoke(HandlerContext $context, $command): \Generator
    {
        if (!$command instanceof LoadLocaleCommand) {
            return;
        }

        list ($dir, $ext) = [$context['dir'], $context['ext']];
        list ($locale, $textDomain) = [$command->getLocale(), $command->getTextDomain()];
        $dir = $textDomain === 'default' ? $dir : "$dir/$textDomain";

        foreach (Glob::glob("$dir/$locale.{{$ext}}", Glob::GLOB_BRACE) as $file) {
            yield from $context->run(new LoadFileCommand($file));
        }
    }
}
