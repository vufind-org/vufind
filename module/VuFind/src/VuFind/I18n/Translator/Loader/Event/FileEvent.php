<?php

namespace VuFind\I18n\Translator\Loader\Event;

use Zend\EventManager\Event;

class FileEvent extends Event
{
    /**
     * @var string
     */
    protected $file;

    public function __construct(string $file)
    {
        parent::__construct(self::class);
        $this->file = $file;
    }

    /**
     * @return string
     */
    public function getFile(): string
    {
        return $this->file;
    }
}