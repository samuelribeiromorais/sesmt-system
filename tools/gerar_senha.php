<?php
/**
 * Gerador de hash bcrypt para senhas de usuario
 * Uso: php tools/gerar_senha.php "MinhaSenha123"
 */

if (empty($argv[1])) {
    echo "Uso: php tools/gerar_senha.php \"SuaSenha\"\n";
    exit(1);
}

$senha = $argv[1];
$hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);

echo "\nSenha: {$senha}\n";
echo "Hash:  {$hash}\n\n";
echo "Para inserir no banco:\n";
echo "UPDATE usuarios SET senha_hash = '{$hash}' WHERE email = 'seu@email.com';\n\n";
