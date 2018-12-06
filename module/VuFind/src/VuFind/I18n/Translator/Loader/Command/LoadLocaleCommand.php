<?php

namespace VuFind\I18n\Translator\Loader\Command;

class LoadLocaleCommand
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
     * @param string $locale
     * @return LoadLocaleCommand
     */
    public function setLocale(string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    /**
     * @return string
     */
    public function getTextDomain(): string
    {
        return $this->textDomain;
    }

    /**
     * @param string $textDomain
     * @return LoadLocaleCommand
     */
    public function setTextDomain(string $textDomain): self
    {
        $this->textDomain = $textDomain;
        return $this;
    }
}