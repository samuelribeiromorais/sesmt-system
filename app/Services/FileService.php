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
     * @return string|null Mensagem de erro ou null se válido
     */
    public function validar(array $file): ?string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE   => 'Arquivo excede o limite do servidor.',
                UPLOAD_ERR_FORM_SIZE  => 'Arquivo excede o limite do formulario.',
                UPLOAD_ERR_PARTIAL    => 'Upload incompleto.',
                UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo enviado.',
                UPLOAD_ERR_NO_TMP_DIR => 'Erro de configuração do servidor.',
                UPLOAD_ERR_CANT_WRITE => 'Erro ao gravar arquivo.',
            ];
            return $errors[$file['error']] ?? 'Erro desconhecido no upload.';
        }

        if ($file['size'] > $this->config['max_size']) {
            $maxMb = round($this->config['max_size'] / 1048576);
            return "Arquivo excede o tamanho máximo de {$maxMb}MB.";
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $this->config['allowed_types'])) {
            return 'Tipo de arquivo não permitido. Apenas PDF.';
        }

        return null;
    }

    /**
     * Retorna o nome da pasta do colaborador no formato "ID - NOME"
     */
    public function getDiretorioColaborador(int $colaboradorId, string $nomeCompleto = ''): string
    {
        // Procurar pasta existente que comece com "{id} - "
        $basePath = $this->config['path'];
        $pattern = $basePath . '/' . $colaboradorId . ' - *';
        $matches = glob($pattern);
        if (!empty($matches) && is_dir($matches[0])) {
            return basename($matches[0]);
        }

        // Se tem nome, criar com formato novo
        if ($nomeCompleto !== '') {
            $nome = $this->sanitizarNome($nomeCompleto);
            return $colaboradorId . ' - ' . $nome;
        }

        // Fallback: apenas ID
        return (string)$colaboradorId;
    }

    /**
     * Gera nome legível para o arquivo
     */
    public function gerarNomeArquivo(string $nomeColaborador, string $tipoDocumento, string $dataEmissao, string $ext = 'pdf'): string
    {
        $nome = $this->sanitizarNome($nomeColaborador);
        $tipo = $this->sanitizarNome($tipoDocumento);
        $data = '00.00.0000';
        if ($dataEmissao && $dataEmissao !== '0000-00-00') {
            $dt = \DateTime::createFromFormat('Y-m-d', $dataEmissao);
            if ($dt) $data = $dt->format('d.m.Y');
        }
        return $nome . ' - ' . $tipo . ' - ' . $data . '.' . $ext;
    }

    /**
     * Remove caracteres invalidos de nomes de arquivo/pasta
     */
    private function sanitizarNome(string $nome): string
    {
        $nome = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], '', $nome);
        $nome = preg_replace('/\s+/', ' ', trim($nome));
        if (mb_strlen($nome) > 120) $nome = mb_substr($nome, 0, 120);
        return $nome;
    }

    /**
     * Salva arquivo no storage com nome legível
     * @return array ['path' => string, 'hash' => string] ou ['error' => string]
     */
    public function salvar(array $file, int $colaboradorId, string $nomeColaborador = '', string $tipoDocumento = '', string $dataEmissao = ''): array
    {
        $hash = hash_file('sha256', $file['tmp_name']);
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        $pastaNome = $this->getDiretorioColaborador($colaboradorId, $nomeColaborador);

        // Gerar nome legível ou fallback para hash
        if ($nomeColaborador !== '' && $tipoDocumento !== '') {
            $safeName = $this->gerarNomeArquivo($nomeColaborador, $tipoDocumento, $dataEmissao, $ext);
        } else {
            $safeName = $hash . '.' . $ext;
        }

        $dir = $this->config['path'] . '/' . $pastaNome;
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $destPath = $dir . '/' . $safeName;

        // Se já existe arquivo com mesmo nome, adicionar sufixo
        if (file_exists($destPath)) {
            $base = pathinfo($safeName, PATHINFO_FILENAME);
            $counter = 2;
            while (file_exists($dir . '/' . $base . ' (' . $counter . ').' . $ext)) {
                $counter++;
            }
            $safeName = $base . ' (' . $counter . ').' . $ext;
            $destPath = $dir . '/' . $safeName;
        }

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return ['error' => 'Erro ao mover arquivo para o storage.'];
        }

        return [
            'path' => $pastaNome . '/' . $safeName,
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
