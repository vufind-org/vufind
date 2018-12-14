<?php

namespace VuFind\I18n\Translator\Loader\Event;

use Zend\EventManager\Event;

class ExtensionEvent extends Event
{
    /**
     * @var string
     */
    protected $extendingFile;

    /**
     * @var string[]
     */
    protected $extendedFiles;

    /**
     * LoadExtendedFilesEvent constructor.
     * @param string $file
     * @param string[] $files
     */
    public function __construct(string $file, array $files)
    {
        parent::__construct(self::class);
        $this->extendingFile = $file;
        $this->extendedFiles = $files;

    }

    /**
     * @return string
     */
    public function getExtendingFile(): string
    {
        return $this->extendingFile;
    }

    /**
     * @return string[]
     */
    public function getExtendedFiles(): array
    {
        return $this->extendedFiles;
    }
}