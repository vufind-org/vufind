<?php

namespace VuFind\Config\Provider;

use Webmozart\Glob\Glob as Globber;
use Zend\Config\Factory;

class Glob
{
    protected $baseLen;
    protected $pattern;

    public function __construct($pattern, $base = '')
    {
        $this->baseLen = strlen($base);
        $this->pattern = $base . $pattern;
    }

    public function __invoke()
    {
        $glob = Globber::glob($this->pattern);
        $data = array_map([Factory::class, 'fromFile'], $glob);
        $list = array_map([$this, 'nest'], $glob, $data);
        return array_merge(...$list);
    }

    protected function nest($path, $data)
    {
        foreach ($this->getKeys($path) as $key) {
            $data = [$key => $data];
        }
        return $data;
    }

    protected function getKeys($path)
    {
        $path = substr_replace($path, "", 0, $this->baseLen);
        $offset = strlen(pathinfo($path, PATHINFO_EXTENSION)) + 1;
        $path = trim(substr_replace($path, '', -$offset), '/');
        return array_reverse(explode('/', $path));
    }
}
