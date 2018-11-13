<?php

namespace VuFind\I18n\Translator\Resolver;

class LocalFile implements ResolverInterface
{
    /**
     * @var string
     */
    protected $ext;

    /**
     * @var string
     */
    protected $dir;


    public function __construct(string $ext, string $dir)
    {
        list ($this->ext, $this->dir) = [$ext, $dir];
    }

    public function resolve(string $locale, string $textDomain): string
    {
        $dirname = "$this->dir" . ($textDomain === 'default' ? '' : "/$textDomain");
        return is_file($path = "$dirname/$locale.$this->ext") ? $path : '';
    }
}