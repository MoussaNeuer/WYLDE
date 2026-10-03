<?php

declare(strict_types=1);

namespace App\Validators;

use App\Services\UploadService;

/**
 * Validation d'un média téléversé (API médias / drag & drop).
 */
class UploadValidator extends Validator
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [], private ?array $file = null)
    {
        parent::__construct($data);
    }

    public function validate(): void
    {
        $file = $this->file;

        if ($file === null) {
            $this->addError('file', __('validation.required', ['field' => 'file']));

            return;
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            $this->addError('file', 'Fichier invalide (code ' . $error . ').');

            return;
        }

        $size = (int) ($file['size'] ?? 0);

        if ($size <= 0) {
            $this->addError('file', 'Fichier vide.');

            return;
        }

        if ($size > UploadService::maxSize()) {
            $this->addError('file', 'Fichier trop volumineux (max ' . UploadService::formatSize(UploadService::maxSize()) . ').');
        }

        $extension = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

        if (!in_array($extension, UploadService::allowedExtensions(), true)) {
            $this->addError('file', 'Format non autorisé.');
        }
    }

    /** Ordre du drag & drop : tableau d'entiers sans doublon. */
    public function validateOrder(mixed $order): void
    {
        if (!is_array($order) || $order === []) {
            return;
        }

        $ids = array_map('intval', $order);

        if (count($ids) !== count(array_unique($ids))) {
            $this->addError('order', 'Ordre envoyé avec des doublons.');
        }
    }
}