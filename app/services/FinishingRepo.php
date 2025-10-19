<?php
namespace App\Services;

use PDO;

class FinishingRepo
{
    public function __construct(private PDO $db) {}

    /* ========= Lecturas ========= */

    public function all(bool $onlyActive = false): array {
        $sql = "SELECT * FROM finishings";
        if ($onlyActive) $sql .= " WHERE active=1";
        $sql .= " ORDER BY name ASC";
        $rows = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        return $this->attachTiers($rows);
    }

    public function find(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM finishings WHERE id=:id");
        $stmt->execute([':id'=>$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['tiers'] = $this->tiers($id);
        return $row;
    }

    public function tiers(int $finishingId): array {
        $stmt = $this->db->prepare("SELECT * FROM finishing_tiers WHERE finishing_id=:id ORDER BY min_qty ASC");
        $stmt->execute([':id'=>$finishingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Devuelve solo las columnas que consumen los calculadores (pricing, setup, tiers) */
    public function asSpecs(array $finishingIds): array {
        if (!$finishingIds) return [];
        $in  = implode(',', array_fill(0, count($finishingIds), '?'));
        $stmt = $this->db->prepare("SELECT * FROM finishings WHERE id IN ($in) AND active=1");
        $stmt->execute($finishingIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $rows = $this->attachTiers($rows);

        // Mapea a formato de spec de acabado esperado por los calculadores
        $out = [];
        foreach ($rows as $r) {
            $spec = [
                'nombre'  => $r['name'],
                'pricing' => $r['pricing'],
                'setup'   => (float)$r['setup'],
            ];
            // tiers -> [ ['min'=>..,'max'=>..,'cost'=>..], ... ]
            if (!empty($r['tiers'])) {
                $spec['tiers'] = array_map(fn($t)=>[
                    'min'=>(int)$t['min_qty'],
                    'max'=>(int)$t['max_qty'],
                    'cost'=>(float)$t['cost'],
                ], $r['tiers']);
            }
            $out[] = $spec;
        }
        return $out;
    }

    /* ========= Escrituras ========= */

    public function create(string $name, string $pricing, float $setup, int $active, ?string $notes): int {
        $stmt = $this->db->prepare("
          INSERT INTO finishings (name, pricing, setup, active, notes)
          VALUES (:name,:pricing,:setup,:active,:notes)
        ");
        $stmt->execute([
            ':name'=>$name, ':pricing'=>$pricing, ':setup'=>$setup, ':active'=>$active, ':notes'=>$notes
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, string $name, string $pricing, float $setup, int $active, ?string $notes): bool {
        $stmt = $this->db->prepare("
          UPDATE finishings
          SET name=:name, pricing=:pricing, setup=:setup, active=:active, notes=:notes
          WHERE id=:id
        ");
        return $stmt->execute([
            ':id'=>$id, ':name'=>$name, ':pricing'=>$pricing, ':setup'=>$setup, ':active'=>$active, ':notes'=>$notes
        ]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM finishings WHERE id=:id");
        return $stmt->execute([':id'=>$id]);
    }

    public function addTier(int $finishingId, int $min, int $max, float $cost): bool {
        $stmt = $this->db->prepare("
          INSERT INTO finishing_tiers (finishing_id, min_qty, max_qty, cost)
          VALUES (:fid,:min,:max,:cost)
        ");
        return $stmt->execute([':fid'=>$finishingId, ':min'=>$min, ':max'=>$max, ':cost'=>$cost]);
    }

    public function deleteTier(int $tierId): bool {
        $stmt = $this->db->prepare("DELETE FROM finishing_tiers WHERE id=:id");
        return $stmt->execute([':id'=>$tierId]);
    }

    /* ========= Helpers ========= */

    private function attachTiers(array $rows): array {
        if (!$rows) return [];
        $ids = array_column($rows, 'id');
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("SELECT * FROM finishing_tiers WHERE finishing_id IN ($in) ORDER BY min_qty ASC");
        $stmt->execute($ids);
        $tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $byFin = [];
        foreach ($tiers as $t) {
            $byFin[$t['finishing_id']][] = $t;
        }
        foreach ($rows as &$r) {
            $r['tiers'] = $byFin[$r['id']] ?? [];
        }
        return $rows;
    }
}
