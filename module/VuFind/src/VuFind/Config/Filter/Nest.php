<?php

namespace VuFind\Config\Filter;

use Zend\EventManager\Filter\FilterIterator;

class Nest
{
    /**
     * Length of base path to be stripped off file paths
     *
     * @var int
     */
    protected $baseLen;

    public function __construct($baseLen)
    {
        $this->baseLen = $baseLen;
    }

    public function __invoke($ctx, array $args, FilterIterator $chain): array
    {
        list($path, $data) = $args;
        foreach ($this->getKeys($path) as $key) {
            $data = [$key => $data];
        }

        return $data;
    }


    /**
     * Strips base path and extension and returns an array of the remaining
     * segments in reversed order.
     *
     * @param string $path
     *
     * @return array
     */
    protected function getKeys(string $path): array
    {
        $path = substr_replace($path, "", 0, $this->baseLen);
        $offset = strlen(pathinfo($path, PATHINFO_EXTENSION)) + 1;
        $path = trim(substr_replace($path, '', -$offset), '/');
        return array_reverse(explode('/', $path));
    }
}