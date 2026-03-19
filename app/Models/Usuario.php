<?php

namespace App\Models;

use App\Core\Model;

class Usuario extends Model
{
    protected string $table = 'usuarios';

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function incrementLoginAttempts(int $id): void
    {
        $config = require dirname(__DIR__) . '/config/app.php';
        $maxAttempts = $config['session']['max_attempts'];
        $lockoutTime = $config['session']['lockout_time'];

        $this->db->prepare(
            "UPDATE usuarios SET tentativas_login = tentativas_login + 1,
             bloqueado_ate = IF(tentativas_login + 1 >= :max, DATE_ADD(NOW(), INTERVAL :lockout SECOND), bloqueado_ate)
             WHERE id = :id"
        )->execute([
            'max'     => $maxAttempts,
            'lockout' => $lockoutTime,
            'id'      => $id,
        ]);
    }

    public function resetLoginAttempts(int $id): void
    {
        $this->update($id, [
            'tentativas_login' => 0,
            'bloqueado_ate'    => null,
            'ultimo_login'     => date('Y-m-d H:i:s'),
        ]);
    }

    public function isLocked(array $user): bool
    {
        if (empty($user['bloqueado_ate'])) {
            return false;
        }
        return strtotime($user['bloqueado_ate']) > time();
    }
}
