<?php

namespace VuFind\I18n\Translator\Loader\Handler;

use VuFind\I18n\Translator\Loader\Command\LoadExtendedFilesCommand;
use VuFind\I18n\Translator\Loader\Command\LoadFileCommand;
use VuFind\I18n\Translator\TranslatorRuntimeException;
use Zend\Uri\Uri;

class ExtendedFilesHandler implements HandlerInterface
{
    public function __invoke(HandlerContext $context, $command): \Generator
    {
        if (!$command instanceof LoadExtendedFilesCommand) {
            return;
        }

        $extendingUri = new Uri($extendingFile = $command->getExtendingFile());

        foreach ($command->getExtendedFiles() as $extendedFile) {
            $extendedUri = Uri::merge($extendingUri, $extendedFile);
            $loadExtendedFileCommand = new LoadFileCommand((string)$extendedUri);
            foreach ($context->run($loadExtendedFileCommand) as $ancestorFile => $data) {
                if ($extendingFile === $ancestorFile) {
                    throw new TranslatorRuntimeException("Circular chain of language files at $extendingFile");
                }
                yield $ancestorFile => $data;
            }
        }

    }
}