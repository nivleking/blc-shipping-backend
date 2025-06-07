<?php

namespace App\Utilities;

use App\Models\SimulationLog;
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
     * Default encoding format, can be overridden per method call
     */
    protected $defaultEncodingFormat = 'json';

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
     * Simpan data di cache dengan format yang dapat ditentukan
     *
     * @param string $key Key untuk menyimpan data
     * @param mixed $data Data yang akan disimpan
     * @param int|null $ttl TTL dalam detik
     * @param bool|null $useGzip Gunakan Gzip compression
     * @return bool Berhasil atau tidak
     */
    public function set(string $key, mixed $data, ?int $ttl = null, ?bool $useGzip = null): bool
    {
        try {
            $ttlValue = $ttl ?? $this->defaultTtl;

            // JSON encode the data first
            $jsonData = json_encode($data);
            if ($jsonData === false) {
                // Handle JSON encoding failure
                return false;
            }

            if ($useGzip === true) {
                // Add a prefix to identify Gzip compressed data
                if (function_exists('gzencode')) {
                    $serialized = 'GZ:' . gzencode($jsonData, 6); // Level 6 is a good balance
                } else {
                    // Fall back to JSON if gzencode is not available
                    $serialized = 'JS:' . $jsonData;
                }
            } else {
                // Add a prefix to identify JSON data
                $serialized = 'JS:' . $jsonData;
            }

            $result = Redis::setex($key, $ttlValue, $serialized);
            return $result && (string)$result === 'OK';
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Ambil data dari cache dengan deteksi format otomatis
     *
     * @param string $key Key untuk mengambil data
     * @param mixed $default Nilai default jika key tidak ada
     * @param bool|null $useGzip Gunakan Gzip decompression
     * @return mixed Data yang diambil
     */
    public function get(string $key, mixed $default = null, ?bool $useGzip = null): mixed
    {
        try {
            if (Redis::exists($key)) {
                $cachedData = Redis::get($key);

                // Check for format prefix
                if (substr($cachedData, 0, 3) === 'GZ:') {
                    // This is Gzip compressed data
                    if (function_exists('gzdecode')) {
                        try {
                            $decompressed = gzdecode(substr($cachedData, 3));
                            if ($decompressed === false) {
                                return $default;
                            }
                            return json_decode($decompressed, true);
                        } catch (Exception $e) {
                            // Fall through to default if decompression fails
                        }
                    }
                } else if (substr($cachedData, 0, 3) === 'JS:') {
                    // This is JSON data
                    return json_decode(substr($cachedData, 3), true);
                } else if (substr($cachedData, 0, 3) === 'MP:') {
                    return $default;
                }

                // Legacy data with no prefix - assume it's JSON
                return json_decode($cachedData, true);
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

    /**
     * Hapus semua cache keys yang terkait dengan sebuah room.
     *
     * @param string $roomId ID room yang cache-nya akan dihapus.
     * @return void
     */
    public function deleteAllRoomCacheKeys(string $roomId): void
    {
        // 1. Cache untuk containers
        $containerCacheKey = $this->generateKey('containers', ['room' => $roomId]);
        $this->delete($containerCacheKey);

        // 2. Cache for simulation logs - manual deletion
        $this->deleteAllSimulationLogCaches($roomId);
    }

    /**
     * Hapus semua simulation log cache keys untuk room tertentu.
     * Pendekatan manual untuk menghindari penggunaan pattern matching.
     *
     * @param string $roomId ID room yang cache-nya akan dihapus.
     * @return int Jumlah key yang berhasil dihapus
     */
    private function deleteAllSimulationLogCaches(string $roomId): int
    {
        try {
            // Get all relevant data from the database
            $simulationLogs = SimulationLog::where('room_id', $roomId)
                ->select('user_id', 'section', 'round')
                ->distinct()
                ->get();

            $deletedCount = 0;

            foreach ($simulationLogs as $log) {
                // Get user ID from the log
                $userId = $log->user_id;

                // Delete room+user+section=all+round=all
                $key = "simulation_logs:room:{$roomId}:user:{$userId}:section:all:round:all";
                if ($this->delete($key)) {
                    $deletedCount++;
                }

                // Delete room+user+specific section+round=all
                $key = "simulation_logs:room:{$roomId}:user:{$userId}:section:{$log->section}:round:all";
                if ($this->delete($key)) {
                    $deletedCount++;
                }

                // Delete room+user+section=all+specific round
                $key = "simulation_logs:room:{$roomId}:user:{$userId}:section:all:round:{$log->round}";
                if ($this->delete($key)) {
                    $deletedCount++;
                }

                // Delete room+user+specific section+specific round
                $key = "simulation_logs:room:{$roomId}:user:{$userId}:section:{$log->section}:round:{$log->round}";
                if ($this->delete($key)) {
                    $deletedCount++;
                }
            }

            return $deletedCount;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
