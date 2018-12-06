<?php

namespace VuFind\I18n\Translator\Loader\Command;

class LoadFileCommand
{
    /**
     * @var string
     */
    protected $file;

    public function __construct($file)
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

    /**
     * @param string $file
     * @return LoadFileCommand
     */
    public function setFile(string $file): self
    {
        $this->file = $file;
        return $this;
    }
}