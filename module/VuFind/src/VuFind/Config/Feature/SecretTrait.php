<?php

namespace VuFind\Config\Feature;

use Laminas\Config\Config;

trait SecretTrait
{
    /**
     * @param Config|array|null $config The config to read from
     * @param string            $key    The key to retrieve
     * @param bool              $trim   (default: false) trim the input config
     *
     * @return string|null
     */
    function getSecretFromConfigOrSecretFile(Config|array|null $config, string $key, bool $trim = false): ?string
    {
        if (!$config) {
            return null;
        }
        if ($config instanceof Config) {
            $config = $config->toArray();
        }
        if ($config[$key . '_file']) {
            $value = file_get_contents($config[$key . '_file']);
        } else {
            $value = $config[$key];
        }
        return ($trim ? trim($value) : $value) ?? null;
    }
}