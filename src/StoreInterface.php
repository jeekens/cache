<?php declare(strict_types=1);

namespace Jeekens\Cache;


interface StoreInterface
{
    /**
     * 设置缓存前缀
     *
     * @param string $prefix
     *
     * @return mixed
     */
    public function setPrefix(string $prefix);

    /**
     * 获取缓存值
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * 写入缓存
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $seconds
     *
     * @return mixed
     */
    public function put(string $key, $value, int $seconds = 0);

    /**
     * 删除缓存
     *
     * @param string $key
     *
     * @return mixed
     */
    public function delete(string $key);

    /**
     * 如果缓存不存在则写入
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $seconds
     *
     * @return mixed
     */
    public function ifPut(string $key, $value, int $seconds = 0);

    /**
     * 判断缓存是否存在
     *
     * @param string $key
     *
     * @return bool
     */
    public function exists(string $key);

    /**
     * 自增
     *
     * @param string $key
     * @param int $value
     *
     * @return mixed
     */
    public function increment(string $key, int $value = 1);

    /**
     * 自减
     *
     * @param string $key
     * @param int $value
     *
     * @return mixed
     */
    public function decrement(string $key, int $value = 1);

    /**
     * flush
     *
     * @return bool
     */
    public function flush();
}