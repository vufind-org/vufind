<?php

namespace VuFind\I18n\Translator\Loader\Handler;

use VuFind\I18n\Translator\Loader\Handler\Action\ActionInterface;
use VuFind\I18n\Translator\Loader\Handler\Action\FileAction;
use VuFind\I18n\Translator\TranslatorRuntimeException;
use Zend\Uri\Uri;

class ExtensionHandler implements HandlerInterface
{
    use HandlerTrait;

    public function canHandle(ActionInterface $action): bool
    {
        return $action instanceof FileAction;
    }

    protected function doHandle(FileAction $action, HandlerChain $chain): \Generator
    {
        foreach ($chain->next($action) as $file => $data) {
            yield $file => $data;
            $fileUri = new Uri($file);
            $extendedFiles = is_string($extendedFiles = $data['@extends'] ?? null)
                ? explode(',', $extendedFiles) : (array)$extendedFiles;

            foreach ($extendedFiles as $extendedFile) {
                $extendedFile = (string)Uri::merge($fileUri, $extendedFile);
                $transitivelyExtendedFiles = $chain->head(new FileAction($extendedFile));
                foreach ($transitivelyExtendedFiles as $transitivelyExtendedFile => $data) {
                    if ($file === $transitivelyExtendedFile) {
                        throw new TranslatorRuntimeException("Circular chain of language files at: $file");
                    }
                    yield $transitivelyExtendedFile => $data;
                }
            }
        }
    }
}