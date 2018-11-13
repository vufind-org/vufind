<?php

namespace VuFind\I18n\Translator\Resolver;

interface ResolverInterface
{
    public function resolve(string $locale, string $textDomain): string;
}