<?php

namespace VuFind\I18n\Translator\Loader\Handler;

interface HandlerInterface
{
    public function __invoke(HandlerContext $context, $command): \Generator;
}