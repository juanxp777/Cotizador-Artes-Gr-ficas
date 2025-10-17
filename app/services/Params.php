<?php
namespace App\Services;

use PDO;

/**
 * Params
 *
 * Servicio para leer parámetros de precios desde BD (clave/valor)
 * con cache en memoria.
 *
 * Tabla sugerida: pricing_params (key VARCHAR PRIMARY KEY, value VARCHAR)
 *
 * Ejemplos de claves:
 * - plate_cost                 -> 20000
 * - offset_millar_cuarto      -> 40000
 * - offset_millar_medio       -> 80000
 * - digital_click_color       -> 200
 * - digital_click_bw          -> 80
 * - laminado_mate_m2          -> 6000
 * - laminado_mate_setup       -> 30000
 */
class Params
{
    private PDO $db;
    private static ?array $cache = null;
    private string $table;

    public function __construct(PDO $db, string $table = 'pricing_params')
    {
        $this->db    = $db;
        $this->table = $table;
    }

    /**
     * Retorna todos los parámetros (cached)
     */
    public function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $sql = "SELECT `key`, `value` FROM `{$this->table}`";
        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $r) {
            // normalizamos keys a snake_case minúscula por si acaso
            $k = strtolower(trim($r['key']));
            $out[$k] = $r['value'];
        }
        self::$cache = $out;
        return self::$cache;
    }

    /**
     * Obtiene un parámetro como string (o default si no existe)
     */
    public function get(string $key, $default = null): mixed
    {
        $k = strtolower(trim($key));
        $all = $this->all();
        return array_key_exists($k, $all) ? $all[$k] : $default;
    }

    /**
     * Obtiene un parámetro como float (convierte coma a punto si viene así)
     */
    public function getFloat(string $key, float $default = 0.0): float
    {
        $val = $this->get($key, null);
        if ($val === null || $val === '') return $default;
        if (is_string($val)) $val = str_replace(',', '.', $val);
        return (float)$val;
    }

    /**
     * Refresca cache (por si cambiaste valores en el admin)
     */
    public function refresh(): void
    {
        self::$cache = null;
        $this->all();
    }

    /**
     * Guarda o actualiza un parámetro en la BD
     * (lo usaremos desde un panel admin sencillo).
     */
    public function set(string $key, string $value): bool
    {
        $k = strtolower(trim($key));
        $sql = "INSERT INTO `{$this->table}` (`key`, `value`)
                VALUES (:k, :v)
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)";
        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([':k' => $k, ':v' => $value]);
        if ($ok) $this->refresh();
        return $ok;
    }
}
