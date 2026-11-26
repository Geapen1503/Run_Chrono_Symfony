<?php

namespace App\Edition\Service;

use App\Edition\Model\EditionPayload;
use App\Edition\Support\LegacyStringHelper;

/**
 * Propositions id_html et noms de tables MySQL.
 */
final class EditionNamingService
{
    public function suggestTableNames(string $slug, string $year): array
    {
        $base = LegacyStringHelper::slugify(strtolower(str_replace(' ', '_', $slug)));
        $base = preg_replace('/^20\d{6}_/', '', $base) ?? $base;

        return [
            'table_run' => 'run_'.$base.'_'.$year,
            'table_dag' => 'dag_import_'.$base.'_'.$year,
        ];
    }

    public function assertSafeIdHtml(string $idHtml): void
    {
        if (!preg_match('/^20\d{6}_[A-Za-z0-9_]+$/', $idHtml)) {
            throw new \InvalidArgumentException('id_html invalide.');
        }
    }

    public function assertSafeTableName(string $name, string $prefix): void
    {
        $pattern = $prefix === 'run'
            ? '/^run_[a-z0-9_]+$/'
            : '/^dag_import_[a-z0-9_]+$/';

        if (!preg_match($pattern, $name)) {
            throw new \InvalidArgumentException(sprintf('Nom de table refusé : %s', $name));
        }
    }

    public function buildFromPayload(EditionPayload $payload): EditionPayload
    {
        $payload->slug = LegacyStringHelper::slugify($payload->slug);
        if ($payload->tableRun === '' || $payload->tableDag === '') {
            $suggest = $this->suggestTableNames($payload->slug, $payload->year());
            $payload->tableRun = $payload->tableRun ?: $suggest['table_run'];
            $payload->tableDag = $payload->tableDag ?: $suggest['table_dag'];
        }

        return $payload;
    }
}