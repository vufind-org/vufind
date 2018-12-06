<?php

namespace VuFind\I18n\Translator\Loader\Command;

class LoadExtendedFilesCommand
{
    /**
     * @var string
     */
    protected $extendingFile;

    /**
     * @var string[]
     */
    protected $extendedFiles;

    public function __construct(string $file, array $files)
    {
        list($this->extendingFile, $this->extendedFiles) = [$file, $files];
    }

    /**
     * @return mixed
     */
    public function getExtendingFile()
    {
        return $this->extendingFile;
    }

    /**
     * @param mixed $extendingFile
     * @return LoadExtendedFilesCommand
     */
    public function setExtendingFile($extendingFile)
    {
        $this->extendingFile = $extendingFile;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getExtendedFiles()
    {
        return $this->extendedFiles;
    }

    /**
     * @param mixed $extendedFiles
     * @return LoadExtendedFilesCommand
     */
    public function setExtendedFiles($extendedFiles)
    {
        $this->extendedFiles = $extendedFiles;
        return $this;
    }
}