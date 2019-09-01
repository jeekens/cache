<?php declare(strict_types=1);

namespace Jeekens\Cache;

use function array_merge;
use function array_walk;
use function call_user_func_array;

/**
 * Class MultipleCache
 *
 * @package Jeekens\Cache
 */
class MultipleCache implements StoreInterface
{
    /**
     * 缓存存储驱动
     *
     * @var array
     */
    protected $stores;


    public function __construct(StoreInterface ... $stores)
    {
        if ($stores != []) {
            call_user_func_array([$this, 'push'], $stores);
        }
    }

    public function flush()
    {
        if ($this->stores != null) {
            array_walk($this->stores, function (StoreInterface $store) {
                $store->flush();
            });
        }
    }

    public function get(string $key, $default = null)
    {
        if ($this->stores != null) {
            foreach ($this->stores as $store) {
                $content = $store->get($key, $default);

                if ($content !== null) {
                    return $content;
                }
            }
        }

        return $default;
    }

    public function delete(string $key)
    {
        if ($this->stores != null) {
            array_walk($this->stores, function (StoreInterface $store) use ($key) {
                $store->delete($key);
            });
        }
    }

    public function put(string $key, $value, int $seconds = 0)
    {
        if ($this->stores != null) {
            array_walk($this->stores, function (StoreInterface $store) use ($key, $value, $seconds) {
                $store->put($key, $value, $seconds);
            });
        }
    }

    public function setPrefix(string $prefix)
    {
        if ($this->stores != null) {
            array_walk($this->stores, function (StoreInterface $store) use ($prefix) {
                $store->setPrefix($prefix);
            });
        }
    }

    /**
     *  自减
     *
     * @param $key
     * @param $value
     *
     * @return mixed
     */
    public function decrement(string $key, int $value = 1)
    {
        return $this->increment($key, $value * -1);
    }


    public function exists(string $key)
    {
        if ($this->stores != null) {
            foreach ($this->stores as $store) {
                if ($store->exists($key)) {
                    return true;
                }
            }
        }

        return false;
    }


    public function ifPut(string $key, $value, int $seconds = 0)
    {
        if (! $this->exists($key)) {
            foreach ($this->stores as $store) {
                $store->put($key, $value, $seconds);
            }
        }
    }

    /**
     * 自增
     *
     * @param $key
     * @param $value
     *
     * @return mixed
     */
    public function increment(string $key, int $value = 1)
    {
        if ($this->stores != null) {
            return array_walk($this->stores, function (StoreInterface $store) use ($key, $value) {
                $store->increment($key, $value);
            });
        }

        return false;
    }

    /**
     * 添加存储驱动
     *
     * @param StoreInterface $store
     * @param StoreInterface ...$stores
     *
     */
    protected function push(StoreInterface $store, StoreInterface ...$stores)
    {
        $notEmpty = ! empty($stores);

        if ($notEmpty) {
            $this->stores = array_merge($this->stores ?? [], [$store], $stores);
        }
    }
}