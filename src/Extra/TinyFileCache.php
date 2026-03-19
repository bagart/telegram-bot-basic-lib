<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic\Extra;

use DateInterval;
use FilesystemIterator;
use Psr\SimpleCache\CacheInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class TinyFileCache implements CacheInterface
{
    protected $path;

    public function __construct(string $cacheDir = 'storage/lib/cache')
    {
        $this->path = rtrim($cacheDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (!is_dir($this->path)) {
            mkdir($this->path, 0777, true);
        }
    }

    public function getMultiple($keys, $default = null): iterable
    {
        return [];
    }

    public function setMultiple($values, $ttl = null): bool
    {
        return true;
    }

    public function deleteMultiple($keys): bool
    {
        return true;
    }

    public function has($key): bool
    {
        return $this->get($key) !== null;
    }

    public function get($key, $default = null): mixed
    {
        $file = $this->getFilePath($key);
        if (!file_exists($file)) {
            return $default;
        }

        $content = file_get_contents($file);
        $data = unserialize($content);

        if (time() > $data['expires']) {
            $this->delete($key);

            return $default;
        }

        return $data['value'];
    }

    protected function getFilePath($key): string
    {
        $hash = md5($key);
        // Создаем структуру папок /a1/b2/ для производительности
        $dir = $this->path.substr($hash, 0, 2).DIRECTORY_SEPARATOR.substr($hash, 2, 2);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir.DIRECTORY_SEPARATOR.$hash;
    }

    public function delete($key): bool
    {
        $file = $this->getFilePath($key);

        return file_exists($file) ? unlink($file) : true;
    }

    public function put(string $key, $value, $ttl = null): bool
    {
        return $this->set($key, $value, $ttl);
    }

    public function set($key, $value, $ttl = null): bool
    {
        $seconds = $this->resolveTtl($ttl);
        $data = serialize(['value' => $value, 'expires' => time() + $seconds]);

        return (bool)file_put_contents($this->getFilePath($key), $data, LOCK_EX);
    }

    private function resolveTtl($ttl): int
    {
        if ($ttl instanceof DateInterval) {
            return $ttl->s + ($ttl->i * 60) + ($ttl->h * 3600);
        }

        return (int)($ttl ?? 3600);
    }

    public function clear(): bool
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }

        return true;
    }

    public function increment(string $key, int $offset = 1, int $ttl = 3600): int
    {
        $file = $this->getFilePath($key);
        $handle = fopen($file, 'c+');
        flock($handle, LOCK_EX);

        $content = stream_get_contents($handle);
        $data = $content ? unserialize($content) : null;

        $newValue = (!$data || time() > $data['expires']) ? $offset : (int)$data['value'] + $offset;
        $newData = serialize(['value' => $newValue, 'expires' => time() + $this->resolveTtl($ttl)]);

        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, $newData);
        flock($handle, LOCK_UN);
        fclose($handle);

        return $newValue;
    }
}
