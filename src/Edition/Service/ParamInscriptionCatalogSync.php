<?php

namespace App\Edition\Service;

use App\Edition\Model\EditionPayload;
use App\Edition\Support\LegacyStringHelper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;

/**
 * UPSERT catalogue param_inscription_manuelle (comme alwaysdata_sql / localhost_sql).
 * Complète automatiquement les colonnes NOT NULL sans défaut (strict MySQL prod).
 */
final class ParamInscriptionCatalogSync
{
    /** @var array<string, Column>|null */
    private ?array $paramInscriptionColumns = null;

    public function __construct(
        private Connection $connection,
        private LegacyPaths $paths,
    ) {
    }

    public function sync(EditionPayload $payload): void
    {
        $idHtml = $payload->idHtml();
        $fichierCourse = $this->paths->fichierCourseCatalog($idHtml, $payload->year());

        $this->connection->executeStatement(
            'DELETE FROM param_inscription_manuelle WHERE id_html = :id',
            ['id' => $idHtml],
        );

        $courses = $payload->courses;
        $nom = static function (int $i) use ($courses): string {
            foreach ($courses as $course) {
                if ($course->index === $i) {
                    return $course->nomCourse;
                }
            }

            return '';
        };

        $row = [
            'id_html' => $idHtml,
            'table_depart_tspreel' => $payload->tableDepartTspreel,
            'date_evenement' => $payload->eventDate,
            'fichier_course' => $fichierCourse,
            'lib_date_evenement' => LegacyStringHelper::strToNoAccent($payload->libelleDate),
            'evenement' => LegacyStringHelper::strToNoAccent($payload->eventName),
            'nom_course_1' => $nom(1),
            'nom_course_2' => $nom(2),
            'nom_course_3' => $nom(3),
            'nom_course_4' => $nom(4),
            'nom_course_5' => $nom(5),
            'nom_course_6' => $nom(6),
            'table_dag' => $payload->tableDag,
            'table_run' => $payload->tableRun,
            'table_inter' => $payload->tableIntermediaire,
            'categorie_ffa' => $payload->categorieFfa,
            'arg_1' => $payload->siteInscription,
            'arg_2' => $payload->afficheFilename,
            'arg_3' => $payload->textePresentation !== ''
                ? LegacyStringHelper::strToNoAccent($payload->textePresentation)
                : ' ',
            'arg_4' => $payload->afficheTarifFilename,
            'arg_5' => $payload->reglementFilename,
            'arg_6' => $payload->listeInscription,
            'podium' => $payload->podium,
            'podium_limit' => $payload->podiumLimit,
            'diplome' => $payload->diplome,
            'custom' => '',
            'custom_resultat' => '',
            'heure_depart_course_1' => $this->heureLegacy($payload->heuresDepart[0] ?? ''),
            'heure_depart_course_2' => $this->heureLegacy($payload->heuresDepart[1] ?? ''),
            'heure_depart_course_3' => $this->heureLegacy($payload->heuresDepart[2] ?? ''),
            'heure_depart_course_4' => $this->heureLegacy($payload->heuresDepart[3] ?? ''),
            'heure_depart_course_5' => $this->heureLegacy($payload->heuresDepart[4] ?? ''),
            'heure_depart_course_6' => $this->heureLegacy($payload->heuresDepart[5] ?? ''),
        ];

        $row = $this->fillMissingNotNullColumns($row, $payload);
        $this->insertRow($row);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function fillMissingNotNullColumns(array $row, EditionPayload $payload): array
    {
        foreach ($this->paramInscriptionColumns() as $name => $column) {
            if ($name === 'id' || \array_key_exists($name, $row)) {
                continue;
            }
            if (!$column->getNotnull()) {
                continue;
            }
            if ($column->getDefault() !== null) {
                continue;
            }

            $row[$name] = $this->legacyDefaultForColumn($name, $column, $payload);
        }

        return $row;
    }

    private function legacyDefaultForColumn(string $name, Column $column, EditionPayload $payload): mixed
    {
        if (str_starts_with($name, 'heure_depart_course_')) {
            return '09:00:00';
        }
        if (str_starts_with($name, 'nom_course_')) {
            return '';
        }
        if ($name === 'podium') {
            return $payload->podium;
        }
        if ($name === 'podium_limit' || $name === 'limit') {
            return $payload->podiumLimit;
        }
        if ($name === 'table_inter' || $name === 'table_intermediaire') {
            return $payload->tableIntermediaire;
        }
        if ($name === 'custom' || $name === 'custom_resultat') {
            return '';
        }
        if (str_starts_with($name, 'arg_')) {
            return $name === 'arg_3' ? ' ' : '';
        }
        if ($name === 'date_evenement') {
            return $payload->eventDate;
        }

        $typeName = $column->getType()->getName();

        return match ($typeName) {
            'integer', 'bigint', 'smallint' => 0,
            'date' => $payload->eventDate ?: date('Y-m-d'),
            'time', 'time_immutable' => '09:00:00',
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $row
     */
    private function insertRow(array $row): void
    {
        $columns = [];
        $placeholders = [];
        foreach (\array_keys($row) as $name) {
            $columns[] = '`'.str_replace('`', '``', $name).'`';
            $placeholders[] = ':'.$name;
        }

        $sql = sprintf(
            'INSERT INTO param_inscription_manuelle (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders),
        );

        $this->connection->executeStatement($sql, $row);
    }

    /**
     * @return array<string, Column>
     */
    private function paramInscriptionColumns(): array
    {
        if ($this->paramInscriptionColumns === null) {
            $this->paramInscriptionColumns = $this->connection
                ->createSchemaManager()
                ->listTableColumns('param_inscription_manuelle');
        }

        return $this->paramInscriptionColumns;
    }

    /**
     * Colonnes TIME NOT NULL — évite l’erreur 1364 si le formulaire laisse un champ vide.
     */
    private function heureLegacy(string $value): string
    {
        $value = trim($value);

        return $value !== '' ? $value : '09:00:00';
    }
}