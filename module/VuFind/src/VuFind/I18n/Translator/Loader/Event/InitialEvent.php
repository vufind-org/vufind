<?php

namespace VuFind\I18n\Translator\Loader\Event;

use Zend\EventManager\Event;

class InitialEvent extends Event
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
        parent::__construct(self::class);
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