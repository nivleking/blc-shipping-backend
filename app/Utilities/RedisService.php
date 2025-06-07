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

    protected $encodingFormat = 'msgpack';

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
     * Simpan data di cache menggunakan MessagePack jika tersedia
     */
    public function set(string $key, mixed $data, ?int $ttl = null): bool
    {
        try {
            $ttlValue = $ttl ?? $this->defaultTtl;

            // Gunakan MessagePack jika ekstensi tersedia
            if ($this->encodingFormat === 'msgpack' && extension_loaded('msgpack')) {
                // Tambahkan metadata format untuk menandai bahwa ini MessagePack
                $wrappedData = [
                    'format' => 'msgpack',
                    'data' => $data
                ];
                $serialized = msgpack_pack($wrappedData);
            } else {
                // Fallback ke JSON
                $serialized = json_encode($data);
            }

            $result = Redis::setex($key, $ttlValue, $serialized);
            return $result && (string)$result === 'OK';
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Ambil data dari cache dengan deteksi format otomatis
     */
    public function get(string $key, mixed $default = null): mixed
    {
        try {
            if (Redis::exists($key)) {
                $cachedData = Redis::get($key);

                // Coba unpack sebagai MessagePack
                if ($this->encodingFormat === 'msgpack' && extension_loaded('msgpack')) {
                    try {
                        $unpacked = msgpack_unpack($cachedData);

                        // Cek apakah ini format yang kita buat dengan metadata
                        if (is_array($unpacked) && isset($unpacked['format']) && $unpacked['format'] === 'msgpack') {
                            return $unpacked['data'];
                        }
                    } catch (Exception $e) {
                        // Jika gagal unpack, mungkin ini JSON
                    }
                }

                // Fallback ke JSON
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
