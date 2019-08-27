<?php declare(strict_types=1);

namespace Jeekens\Cache;


use Exception;
use Jeekens\Basics\Fs;
use Throwable;
use Jeekens\Cache\Exception\StoreException;
use function call_user_func;
use function clearstatcache;
use function compact;
use function fclose;
use function file_put_contents;
use function filesize;
use function flock;
use function fopen;
use function fread;
use function is_string;
use function serialize;
use function time;
use function unserialize;
use const LOCK_EX;
use const LOCK_SH;
use const LOCK_UN;

/**
 * Class FileStore
 *
 * @package Jeekens\Cache
 */
class FileStore implements StoreInterface
{

    /**
     * 缓存存储路径
     *
     * @var string
     */
    protected $path;

    /**
     * 缓存前缀
     *
     * @var string
     */
    protected $prefix;

    /**
     * FileStore constructor.
     *
     * @param array|null $option
     *
     * @throws StoreException
     */
    public function __construct(?array $option = null)
    {
        if (empty($option['path']) || !is_string($option['path'])) {
            throw new StoreException('Storage path undefined or formatted incorrectly!');
        }

        $this->path = $option['path'];

        if (!empty($option['prefix'])) {
            $this->setPrefix($option['prefix']);
        }
    }

    /**
     * 获取缓存数据
     *
     * @param string $key
     * @param null $default
     *
     * @return mixed|null
     *
     * @throws \Jeekens\Basics\Exception\FileRemoveException
     */
    public function get(string $key, $default = null)
    {
        return $this->getPayload($key)['data'] ?? $default;
    }

    /**
     * 删除缓存
     *
     * @param string $key
     *
     * @return bool|mixed
     *
     * @throws \Jeekens\Basics\Exception\FileRemoveException
     */
    public function delete(string $key)
    {
        $path = $this->getPath($key);

        return Fs::rmFile($path);
    }

    /**
     * 缓存值自减
     *
     * @param string $key
     * @param int $value
     *
     * @return mixed
     *
     * @throws \Jeekens\Basics\Exception\FileRemoveException
     */
    public function decrement(string $key, int $value = 1)
    {
        return $this->increment($key, $value * -1);
    }

    /**
     * 判断缓存是否存在
     *
     * @param string $key
     *
     * @return bool
     *
     * @throws \Jeekens\Basics\Exception\FileRemoveException
     */
    public function exists(string $key)
    {
        $value = $this->get($key);

        return isset($value);
    }

    /**
     * 如果缓存不存在则写入
     *
     * @param string $key
     * @param mixed $value
     * @param int $seconds
     *
     * @return bool|mixed
     *
     * @throws Throwable
     */
    public function ifPut(string $key, $value, int $seconds = 0)
    {
        if (!$this->exists($key)) {
            return $this->put($key, $value, $seconds);
        } else {
            return false;
        }
    }

    /**
     * 写入缓存
     *
     * @param string $key
     * @param mixed $value
     * @param int $seconds
     *
     * @return bool|mixed
     *
     * @throws Throwable
     */
    public function put(string $key, $value, int $seconds = 0)
    {
        $path = $this->getPath($key);

        if (! $this->ensureCacheDirectoryExists(dirname($path))) {
            return false;
        }

        $result = $this->filePutContents(
            $path, $this->expiration($seconds) . serialize($value), true
        );

        return $result !== false && $result > 0;
    }

    /**
     * 缓存值自增
     *
     * @param string $key
     * @param int $value
     *
     * @return mixed
     *
     * @throws \Jeekens\Basics\Exception\FileRemoveException
     */
    public function increment(string $key, int $value = 1)
    {
        $raw = $this->getPayload($key);

        return call_user_func(function ($newValue) use ($key, $raw) {
            $this->put($key, $newValue, $raw['time'] ?? 0);
        }, ((int)$raw['data']) + $value);
    }

    /**
     * 设置缓存前缀
     *
     * @param string $prefix
     *
     * @return $this|mixed
     */
    public function setPrefix(string $prefix)
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * 刷出缓存
     *
     * @return bool
     *
     * @throws Throwable
     */
    public function flush()
    {
        if (! Fs::directoryExists($this->path , false)) {
            return false;
        }

        return Fs::cleanupDirectory($this->path);
    }

    /**
     * 获取缓存存储路径
     *
     * @param string $key
     *
     * @return string
     */
    protected function getPath(string $key)
    {
        if (!empty($this->prefix)) {
            $key = $this->prefix . $key;
        }

        $parts = array_slice(str_split($hash = sha1($key), 2), 0, 2);

        return $this->path . '/' . implode('/', $parts) . '/' . $hash;
    }

    /**
     * 判断缓存目录是否存在，如不存在则创建，创建失败则抛出异常
     *
     * @param $path
     *
     * @return bool
     *
     * @throws Throwable
     */
    protected function ensureCacheDirectoryExists($path)
    {
        if (! Fs::directoryExists($path, true)) {
            return false;
        }
        return true;
    }

    /**
     * 获取缓存数据
     *
     * @param $key
     *
     * @return array
     *
     * @throws \Jeekens\Basics\Exception\FileRemoveException
     */
    protected function getPayload($key)
    {
        $path = $this->getPath($key);

        try {
            $expire = substr(
                $contents = $this->fileGetContent($path),
                0,
                10
            );
        } catch (Exception $e) {
            return ['data' => null, 'time' => null];
        }

        // 判断缓存是否过期，过期则删除
        if (time() >= $expire) {
            $this->delete($key);
            return ['data' => null, 'time' => null];
        }

        $data = unserialize(substr($contents, 10));
        $time = $expire - time();

        return compact('data', 'time');
    }

    /**
     * 获取文件内容
     *
     * @param $path
     *
     * @return bool|string
     */
    protected function fileGetContent($path)
    {
        $contents = '';

        $handle = fopen($path, 'rb');

        if ($handle) {
            try {
                if (flock($handle, LOCK_SH)) {
                    clearstatcache(true, $path);

                    $contents = fread($handle, filesize($path) ?: 1);

                    flock($handle, LOCK_UN);
                }
            } finally {
                fclose($handle);
            }
        }

        return $contents;
    }

    /**
     * 写入文件内容
     *
     * @param $path
     * @param $contents
     * @param bool $lock
     *
     * @return bool|int
     */
    protected function filePutContents($path, $contents, $lock = false)
    {
        return file_put_contents($path, $contents, $lock ? LOCK_EX : 0);
    }

    /**
     * 获取缓存过期时间
     *
     * @param int $seconds
     *
     * @return int
     */
    protected function expiration(int $seconds = 0)
    {
        $time = time() + $seconds;

        return $seconds === 0 || $time > 9999999999 ? 9999999999 : $time;
    }
}