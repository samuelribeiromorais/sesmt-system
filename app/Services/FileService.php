<?php

namespace App\Services;

class FileService
{
    private array $config;

    public function __construct()
    {
        $appConfig = require dirname(__DIR__) . '/config/app.php';
        $this->config = $appConfig['upload'];
    }

    /**
     * Valida um arquivo de upload
     * @return string|null Mensagem de erro ou null se valido
     */
    public function validar(array $file): ?string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE   => 'Arquivo excede o limite do servidor.',
                UPLOAD_ERR_FORM_SIZE  => 'Arquivo excede o limite do formulario.',
                UPLOAD_ERR_PARTIAL    => 'Upload incompleto.',
                UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo enviado.',
                UPLOAD_ERR_NO_TMP_DIR => 'Erro de configuracao do servidor.',
                UPLOAD_ERR_CANT_WRITE => 'Erro ao gravar arquivo.',
            ];
            return $errors[$file['error']] ?? 'Erro desconhecido no upload.';
        }

        if ($file['size'] > $this->config['max_size']) {
            $maxMb = round($this->config['max_size'] / 1048576);
            return "Arquivo excede o tamanho maximo de {$maxMb}MB.";
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $this->config['allowed_types'])) {
            return 'Tipo de arquivo nao permitido. Apenas PDF.';
        }

        return null;
    }

    /**
     * Salva arquivo no storage
     * @return array ['path' => string, 'hash' => string] ou ['error' => string]
     */
    public function salvar(array $file, int $colaboradorId): array
    {
        $hash = hash_file('sha256', $file['tmp_name']);
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $safeName = $hash . '.' . $ext;

        $dir = $this->config['path'] . '/' . $colaboradorId;
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $destPath = $dir . '/' . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return ['error' => 'Erro ao mover arquivo para o storage.'];
        }

        return [
            'path' => $colaboradorId . '/' . $safeName,
            'hash' => $hash,
        ];
    }

    /**
     * Remove arquivo do storage
     */
    public function remover(string $relativePath): bool
    {
        $fullPath = $this->config['path'] . '/' . $relativePath;
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        return false;
    }

    /**
     * Retorna o caminho absoluto de um arquivo
     */
    public function getCaminhoAbsoluto(string $relativePath): string
    {
        return $this->config['path'] . '/' . $relativePath;
    }
}
