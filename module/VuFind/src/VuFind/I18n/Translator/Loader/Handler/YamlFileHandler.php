<?php

namespace VuFind\I18n\Translator\Loader\Handler;

use Symfony\Component\Yaml\Yaml as Parser;
use VuFind\I18n\Translator\Loader\Command\LoadExtendedFilesCommand;
use VuFind\I18n\Translator\Loader\Command\LoadFileCommand;
use Zend\I18n\Translator\TextDomain;

class YamlFileHandler implements HandlerInterface
{
    public function __invoke(HandlerContext $context, $command): \Generator
    {
        if (!$command instanceof LoadFileCommand || !$this->canLoad($file = $command->getFile())) {
            return;
        }

        yield $file => $data = new TextDomain(Parser::parseFile($file) ?? []);

        $extendedFiles = (array)($data['@extends'] ?? []);
        yield from $context->run(new LoadExtendedFilesCommand($file, $extendedFiles));
    }

    protected function canLoad(string $file): bool
    {
        return in_array(pathinfo($file, PATHINFO_EXTENSION), ['yml', 'yaml']);
    }
}