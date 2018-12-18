<?php

namespace VuFind\I18n\Translator\Loader\Handler;


use VuFind\I18n\Translator\Loader\Handler\Action\ActionInterface;
use VuFind\I18n\Translator\Loader\Handler\Action\FileAction;
use VuFind\I18n\Translator\Loader\Handler\Action\InitialAction;
use Zend\Stdlib\Glob;

class DirectoryHandler implements HandlerInterface
{
    use HandlerTrait;

    /**
     * @var string
     */
    protected $directory;

    /**
     * @var string
     */
    protected $extension;

    public function __construct(array $options)
    {
        $this->directory = $options['dir'];
        $this->extension = $options['ext'];
    }

    public function canHandle(ActionInterface $action): bool
    {
        return $action instanceof InitialAction;
    }

    protected function doHandle(InitialAction $action, HandlerChain $chain): \Generator
    {
        $directory = ($textDomain = $action->getTextDomain()) === 'default'
            ? $this->directory : "$this->directory/$textDomain";

        $globPattern = "$directory/{$action->getLocale()}.{{$this->extension}}";

        foreach (Glob::glob($globPattern, Glob::GLOB_BRACE) as $file) {
            yield from $chain->head(new FileAction($file));
        }

        yield from $chain->next($action);
    }
}