<?php

namespace VuFind\I18n\Translator\Loader\Handler\Action;

class FileAction implements ActionInterface
{
    /**
     * @var string
     */
    protected $file;

    public function __construct(string $file)
    {
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