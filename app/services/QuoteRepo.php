<?php
namespace App\Services;

use PDO;

class QuoteRepo
{
    public function __construct(private PDO $db) {}

    /* ================= Create ================= */

    public function create(array $payload): string
    {
        // payload:
        // - customer_name, customer_email, product_type, margin, tax_pct?, notes?
        // - product (productSpec array)
        // - result (pricingEngine result array)
        // - ladder (optional array)

        $publicId = $this->uuid();

        $sql = "INSERT INTO quotes
            (public_id, customer_name, customer_email, product_type, margin, tax_pct, notes, total_cost, total_pvp, product_json, result_json, ladder_json)
            VALUES
            (:public_id, :customer_name, :customer_email, :product_type, :margin, :tax_pct, :notes, :total_cost, :total_pvp, :product_json, :result_json, :ladder_json)";
        $stmt = $this->db->prepare($sql);

        $result = $payload['result'];
        $totals = $result['totals'] ?? ['cost'=>0,'pvp'=>0];

        $ok = $stmt->execute([
            ':public_id'     => $publicId,
            ':customer_name' => $payload['customer_name'] ?? null,
            ':customer_email'=> $payload['customer_email'] ?? null,
            ':product_type'  => $payload['product_type'] ?? 'otro',
            ':margin'        => (float)($payload['margin'] ?? 0.30),
            ':tax_pct'       => isset($payload['tax_pct']) ? (float)$payload['tax_pct'] : null,
            ':notes'         => $payload['notes'] ?? null,
            ':total_cost'    => (float)($totals['cost'] ?? 0),
            ':total_pvp'     => (float)($totals['pvp'] ?? 0),
            ':product_json'  => json_encode($payload['product'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            ':result_json'   => json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            ':ladder_json'   => !empty($payload['ladder']) ? json_encode($payload['ladder'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,
        ]);

        if (!$ok) {
            throw new \RuntimeException('No se pudo guardar la cotización.');
        }

        $quoteId = (int)$this->db->lastInsertId();

        // Guardar partes (si existen)
        foreach (['cover','interior','insert'] as $pk) {
            if (!empty($result['parts'][$pk])) {
                $part = $result['parts'][$pk];
                $selected = $part['selected'] ?? 'digital';
                $options  = $part['options'] ?? [];

                $this->createPart($quoteId, [
                    'part_key'      => $pk,
                    'part_name'     => $part['part_name'] ?? ucfirst($pk),
                    'selected_key'  => $selected,
                    'selected_cost' => (float)($options[$selected]['total_costo'] ?? 0),
                    'selected_pvp'  => (float)($options[$selected]['pvp'] ?? 0),
                    'options_json'  => json_encode($options, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                    'result_json'   => json_encode($part, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                ]);
            }
        }

        return $publicId;
    }

    private function createPart(int $quoteId, array $data): void
    {
        $sql = "INSERT INTO quote_parts
            (quote_id, part_key, part_name, selected_key, selected_cost, selected_pvp, options_json, result_json)
            VALUES
            (:quote_id, :part_key, :part_name, :selected_key, :selected_cost, :selected_pvp, :options_json, :result_json)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':quote_id'      => $quoteId,
            ':part_key'      => $data['part_key'],
            ':part_name'     => $data['part_name'],
            ':selected_key'  => $data['selected_key'],
            ':selected_cost' => $data['selected_cost'],
            ':selected_pvp'  => $data['selected_pvp'],
            ':options_json'  => $data['options_json'],
            ':result_json'   => $data['result_json'],
        ]);
    }

    /* ================= Read ================= */

    public function getByPublicId(string $publicId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM quotes WHERE public_id=:pid");
        $stmt->execute([':pid'=>$publicId]);
        $q = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$q) return null;
        $q['product'] = json_decode($q['product_json'], true);
        $q['result']  = json_decode($q['result_json'], true);
        $q['ladder']  = $q['ladder_json'] ? json_decode($q['ladder_json'], true) : null;
        $q['parts']   = $this->parts((int)$q['id']);
        return $q;
    }

    public function parts(int $quoteId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM quote_parts WHERE quote_id=:id ORDER BY id ASC");
        $stmt->execute([':id'=>$quoteId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['options'] = json_decode($r['options_json'], true);
            $r['result']  = json_decode($r['result_json'], true);
        }
        return $rows;
    }

    /* ================= Util ================= */

    private function uuid(): string
    {
        // UUIDv4 simple (sin dependencia externa)
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40); // version
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80); // variant
        $hex = bin2hex($d);
        return sprintf('%s-%s-%s-%s-%s',
            substr($hex,0,8), substr($hex,8,4), substr($hex,12,4), substr($hex,16,4), substr($hex,20,12)
        );
    }
}
