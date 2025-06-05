<?php

namespace App\Utilities;

use Illuminate\Support\Facades\Redis;
use Exception;

class RedisService
{
    /**
     * Default TTL untuk item cache (dalam detik)
     * Default: 1 jam
     */
    protected $defaultTtl = 3600;

    /**
     * Generate key cache yang terstandarisasi
     *
     * @param string $prefix Tipe entitas (misal: 'containers', 'room', 'logs')
     * @param array $identifiers Array identifier (misal: ['room_id' => 123])
     * @return string Key cache
     */
    public function generateKey(string $prefix, array $identifiers): string
    {
        $identifierString = implode(':', array_map(function ($key, $value) {
            return "{$key}:{$value}";
        }, array_keys($identifiers), $identifiers));

        return "{$prefix}:{$identifierString}";
    }

    /**
     * Mendapatkan data dari cache atau fetch dari callback jika tidak ada
     *
     * @param string $key Key cache
     * @param callable $callback Fungsi untuk mengambil data jika tidak ada di cache
     * @param int|null $ttl TTL cache dalam detik (null menggunakan default)
     * @return mixed Data dari cache atau hasil fetch
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        try {
            // Coba ambil dari cache dulu
            if (Redis::exists($key)) {
                $cachedData = Redis::get($key);
                return json_decode($cachedData, true);
            }

            // Cache miss - execute callback untuk mendapatkan data baru
            $freshData = $callback();

            // Simpan di cache dengan TTL
            $ttlValue = $ttl ?? $this->defaultTtl;
            Redis::setex($key, $ttlValue, json_encode($freshData));

            return $freshData;
        } catch (Exception $e) {
            // Jika Redis gagal, log error dan kembalikan data baru
            return $callback();
        }
    }

    /**
     * Simpan data di cache
     *
     * @param string $key Key cache
     * @param mixed $data Data yang akan disimpan
     * @param int|null $ttl TTL dalam detik (null menggunakan default)
     * @return bool Berhasil atau tidak
     */
    public function set(string $key, mixed $data, ?int $ttl = null): bool
    {
        try {
            $ttlValue = $ttl ?? $this->defaultTtl;
            $result = Redis::setex($key, $ttlValue, json_encode($data));

            // Check if the operation was successful
            // Predis returns a Status object with value "OK" on success
            return $result && (string)$result === 'OK';
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Ambil data dari cache
     *
     * @param string $key Key cache
     * @param mixed $default Nilai default jika key tidak ditemukan
     * @return mixed Data dari cache atau default
     */
    public function get(string $key, mixed $default = null): mixed
    {
        try {
            if (Redis::exists($key)) {
                return json_decode(Redis::get($key), true);
            }
            return $default;
        } catch (Exception $e) {
            return $default;
        }
    }

    /**
     * Hapus data dari cache
     *
     * @param string $key Key cache
     * @return bool Berhasil atau tidak
     */
    public function delete(string $key): bool
    {
        try {
            $result = Redis::del($key);
            return is_numeric($result) ? $result > 0 : (bool)$result;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Hapus beberapa key berdasarkan pattern
     *
     * @param string $pattern Pattern key yang cocok (misal: "logs:*")
     * @return int Jumlah key yang dihapus
     */
    public function deletePattern(string $pattern): int
    {
        try {
            $keys = Redis::keys($pattern);
            if (empty($keys)) {
                return 0;
            }

            return Redis::del($keys);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Cek apakah key ada di cache
     *
     * @param string $key Key cache
     * @return bool True jika ada
     */
    public function has(string $key): bool
    {
        try {
            $result = Redis::exists($key);
            return is_numeric($result) ? $result > 0 : (bool)$result;
        } catch (Exception $e) {
            return false;
        }
    }
}
