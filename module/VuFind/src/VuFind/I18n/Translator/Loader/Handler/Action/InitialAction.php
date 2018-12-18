<?php

namespace VuFind\I18n\Translator\Loader\Handler\Action;

class InitialAction implements ActionInterface
{
    /**
     * @var string
     */
    protected $locale;

    /**
     * @var string
     */
    protected $textDomain;

    public function __construct(string $locale, string $textDomain)
    {
        $this->locale = $locale;
        $this->textDomain = $textDomain;
    }

    /**
     * @return string
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @return string
     */
    public function getTextDomain(): string
    {
        return $this->textDomain;
    }
}