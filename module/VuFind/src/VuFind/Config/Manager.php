<?php
namespace VuFind\Config;

use Zend\Config\Config;
use Zend\Config\Factory;

class Manager
{
    const CONFIG_PATH = APPLICATION_PATH . '/config/config.php';
    const CONFIG_CACHE_DIR = LOCAL_CACHE_DIR . '/config';
    const ENTIRE_CONFIG_PATH = self::CONFIG_CACHE_DIR . '/entire.php';
    const SPARSE_CONFIG_PATH = self::CONFIG_CACHE_DIR . '/sparse.php';

    /**
     * Contains all aggregated configuration
     *
     * @var Config
     */
    protected $entireConfig;

    /**
     * Contains only required configurations
     *
     * @var Config
     */
    protected $sparseConfig;

    public function get($path = null)
    {
        $data = $this->getData($path);
        return $data instanceof Config ? new Config($data->toArray()) : $data;
    }

    public function reset()
    {
        $this->sparseConfig = $this->entireConfig = null;
        if (file_exists(static::SPARSE_CONFIG_PATH)) {
            unlink(static::SPARSE_CONFIG_PATH);
        }
        if (file_exists(static::ENTIRE_CONFIG_PATH)) {
            unlink(static::ENTIRE_CONFIG_PATH);
        }
    }

    protected function getData($path)
    {
        $path = trim($path, '/');
        $keys = $path ? explode('/', $path) : [];
        
        $config = $this->getSparseConfig();
        
        if ($this->someEqualsTrue($config->loaded, ...$keys)) {
            return $this->getValue($config->content, ...$keys);
        }
                
        $data = $this->getValue($this->getEntireConfig(), ...$keys);
        $this->setValue($config, $data, 'content', ...$keys);
        $this->setValue($config, true, 'loaded', ...$keys);
        
        if (CACHE_ENABLED) {
            Factory::toFile(static::SPARSE_CONFIG_PATH, $config);
        }

        return $data;
    }

    protected function setValue($config, $value, $key, ...$keys)
    {
        if ($keys) {
            $config->$key = $config->$key ?: new Config([], true);
            return $this->setValue($config->$key, $value, ...$keys);
        }
        $config->$key = $value;
    }

    protected function getValue($value, $key = null, ...$keys)
    {
        return $key ? $this->getValue($value->$key, ...$keys) : $value;
    }

    protected function someEqualsTrue($value, $key = null, ...$keys)
    {
        return $value === true || $key && $value->$key
            && $this->someEqualsTrue($value->$key, ...$keys);
    }

    protected function getSparseConfig()
    {
        return $this->sparseConfig ?: $this->loadSparseConfig();
    }

    protected function loadSparseConfig()
    {
        $data = CACHE_ENABLED && file_exists(static::SPARSE_CONFIG_PATH)
            ? (Factory::fromFile(static::SPARSE_CONFIG_PATH))
            : ['loaded' => [], 'content' => []];
        return $this->sparseConfig = new Config($data, true);
    }

    protected function getEntireConfig()
    {
        return $this->entireConfig ?: $this->loadEntireConfig();
    }

    protected function loadEntireConfig()
    {
        $cache = CACHE_ENABLED ? (static::ENTIRE_CONFIG_PATH) : null;
        $data = (require static::CONFIG_PATH)($cache)->getMergedConfig();
        return $this->entireConfig = new Config($data, true);
    }
}
