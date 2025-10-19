<?php
namespace App\Services;

use PDO;

class Auth
{
    public function __construct(private PDO $db) {}

    /* ============ Sesión / Estado ============ */

    public function current(): ?array {
        \start_secure_session();
        return $_SESSION['user'] ?? null;
    }

    public function check(): bool {
        \start_secure_session();
        return !empty($_SESSION['user']);
    }

    public function requireLogin(): void {
        if (!$this->check()) {
            header('Location: /public/admin/login.php');
            exit;
        }
    }

    public function requireRole(string|array $roles): void {
        $this->requireLogin();
        $u = $this->current();
        $roles = (array)$roles;
        if (!in_array(($u['role'] ?? 'viewer'), $roles, true)) {
            http_response_code(403);
            die('No autorizado.');
        }
    }

    /* ============ Login / Logout ============ */

    public function login(string $username, string $password): bool {
        \start_secure_session();
        $u = $this->findByUsername($username);
        if (!$u || !$u['active']) return false;
        if (!password_verify($password, $u['password_hash'])) return false;

        $_SESSION['user'] = [
            'id'    => (int)$u['id'],
            'username' => $u['username'],
            'name'  => $u['name'],
            'email' => $u['email'],
            'role'  => $u['role'],
            'must_change_pass' => (bool)$u['must_change_pass'],
        ];
        return true;
    }

    public function logout(): void {
        \start_secure_session();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure']??false, $p['httponly']??true);
        }
        session_destroy();
    }

    /* ============ Usuarios ============ */

    public function findByUsername(string $username): ?array {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username=:u LIMIT 1");
        $stmt->execute([':u'=>$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id=:id");
        $stmt->execute([':id'=>$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listAll(): array {
        $stmt = $this->db->query("SELECT id, username, name, email, role, active, must_change_pass, created_at FROM users ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int {
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("
          INSERT INTO users (username, name, email, role, password_hash, must_change_pass, active)
          VALUES (:username,:name,:email,:role,:password_hash,:must_change_pass,:active)
        ");
        $stmt->execute([
            ':username' => trim($data['username']),
            ':name'     => trim($data['name']),
            ':email'    => trim($data['email'] ?? ''),
            ':role'     => $data['role'] ?? 'sales',
            ':password_hash' => $hash,
            ':must_change_pass' => !empty($data['must_change_pass']) ? 1 : 0,
            ':active'   => !empty($data['active']) ? 1 : 0,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $stmt = $this->db->prepare("
          UPDATE users SET username=:username, name=:name, email=:email, role=:role, active=:active
          WHERE id=:id
        ");
        return $stmt->execute([
            ':id'=>$id,
            ':username'=>trim($data['username']),
            ':name'=>trim($data['name']),
            ':email'=>trim($data['email'] ?? ''),
            ':role'=>$data['role'] ?? 'sales',
            ':active'=>!empty($data['active']) ? 1 : 0,
        ]);
    }

    public function resetPassword(int $id, string $newPassword, bool $mustChange = true): bool {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE users SET password_hash=:h, must_change_pass=:m WHERE id=:id");
        return $stmt->execute([':h'=>$hash, ':m'=>$mustChange?1:0, ':id'=>$id]);
    }

    public function changeOwnPassword(int $id, string $current, string $new): bool {
        $u = $this->findById($id);
        if (!$u) return false;
        if (!password_verify($current, $u['password_hash'])) return false;
        return $this->resetPassword($id, $new, false);
    }
}
