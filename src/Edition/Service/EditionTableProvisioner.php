<?php

namespace App\Edition\Service;

use Doctrine\DBAL\Connection;

/**
 * Création des tables run_* / dag_import_* en clonant la dernière édition de la même année.
 */
final class EditionTableProvisioner
{
    public function __construct(
        private Connection $connection,
        private EditionNamingService $naming,
    ) {
    }

    /**
     * @return array{warnings: list<string>}
     */
    public function provision(string $tableRun, string $tableDag, string $year): array
    {
        $warnings = [];
        $this->naming->assertSafeTableName($tableRun, 'run');
        $this->naming->assertSafeTableName($tableDag, 'dag_import');

        foreach ([$tableRun => 'run', $tableDag => 'dag_import'] as $table => $kind) {
            if (!$this->connection->createSchemaManager()->tablesExist([$table])) {
                continue;
            }
            $count = (int) $this->connection->fetchOne(
                sprintf('SELECT COUNT(*) FROM `%s`', str_replace('`', '``', $table)),
            );
            if ($count > 0) {
                $warnings[] = sprintf('La table %s existe déjà avec %d ligne(s).', $table, $count);
            }
        }

        $reference = $this->findReferenceTables($year);
        if ($reference === null) {
            $this->createFromEmbeddedDdl($tableRun, $tableDag);

            return ['warnings' => $warnings];
        }

        $this->cloneTableIfMissing($reference['table_run'], $tableRun);
        $this->cloneTableIfMissing($reference['table_dag'], $tableDag);

        return ['warnings' => $warnings];
    }

    /**
     * @return array{table_run: string, table_dag: string}|null
     */
    private function findReferenceTables(string $year): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT table_run, table_dag AS table_dag
             FROM param_inscription_manuelle
             WHERE table_run LIKE :prefix
               AND YEAR(date_evenement) = :year
             ORDER BY date_evenement DESC
             LIMIT 1',
            [
                'prefix' => 'run_%',
                'year' => (int) $year,
            ],
        );

        if (!$row || empty($row['table_run']) || empty($row['table_dag'])) {
            return null;
        }

        return [
            'table_run' => (string) $row['table_run'],
            'table_dag' => (string) $row['table_dag'],
        ];
    }

    private function cloneTableIfMissing(string $source, string $target): void
    {
        if ($this->connection->createSchemaManager()->tablesExist([$target])) {
            return;
        }

        $this->naming->assertSafeTableName($source, str_starts_with($source, 'run_') ? 'run' : 'dag_import');
        $row = $this->connection->fetchAssociative(
            'SHOW CREATE TABLE `'.str_replace('`', '``', $source).'`',
        );
        $createSql = $row['Create Table'] ?? null;
        if (!\is_string($createSql) || $createSql === '') {
            throw new \RuntimeException('Impossible de lire le DDL pour '.$source);
        }

        $createSql = preg_replace(
            '/CREATE TABLE `'.preg_quote($source, '/').'`/',
            'CREATE TABLE IF NOT EXISTS `'.str_replace('`', '``', $target).'`',
            $createSql,
            1,
        );
        if ($createSql === null) {
            throw new \RuntimeException('Échec remplacement nom table '.$target);
        }

        $this->connection->executeStatement($createSql);
    }

    private function createFromEmbeddedDdl(string $tableRun, string $tableDag): void
    {
        if (!$this->connection->createSchemaManager()->tablesExist([$tableDag])) {
            $this->connection->executeStatement($this->dagImportDdl($tableDag));
        }
        if (!$this->connection->createSchemaManager()->tablesExist([$tableRun])) {
            $this->connection->executeStatement($this->runDdl($tableRun));
        }
    }

    private function dagImportDdl(string $table): string
    {
        return "CREATE TABLE IF NOT EXISTS `{$table}` (
          `id_dag` int(5) NOT NULL AUTO_INCREMENT,
          `rang_dag` int(5) NOT NULL,
          `dossard` varchar(10) NOT NULL,
          `date_dag` date NOT NULL,
          `time_dag` time NOT NULL,
          `chrono_php` varchar(20) NOT NULL,
          `chrono_mysql` time NOT NULL,
          `nb_passage` varchar(10) NOT NULL,
          `nom_course` varchar(50) NOT NULL,
          `count_scracth` int(10) NOT NULL,
          `categorie_run` varchar(10) NOT NULL,
          `count_categorie` varchar(10) NOT NULL,
          `sexe` varchar(1) NOT NULL,
          `count_sexe` int(10) NOT NULL,
          `nom_prenom` varchar(80) NOT NULL,
          `nom` varchar(40) NOT NULL,
          `prenom` varchar(40) NOT NULL,
          `club` varchar(50) NOT NULL,
          `licence` varchar(20) NOT NULL,
          `annee` varchar(4) NOT NULL,
          `categorie_2019` varchar(10) NOT NULL,
          `count_categorie_2019` varchar(10) NOT NULL,
          `vitesse_kilo_heure` varchar(20) NOT NULL,
          `vitesse_minute_kilo` varchar(20) NOT NULL,
          PRIMARY KEY (`id_dag`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1";
    }

    private function runDdl(string $table): string
    {
        return "CREATE TABLE IF NOT EXISTS `{$table}` (
          `id_dossard` int(5) NOT NULL AUTO_INCREMENT,
          `dossard` varchar(10) NOT NULL,
          `nom_prenom` varchar(80) NOT NULL,
          `categorie` varchar(10) DEFAULT NULL,
          `sexe` varchar(1) DEFAULT NULL,
          `club` varchar(50) DEFAULT NULL,
          `licence` varchar(20) DEFAULT NULL,
          `distance` varchar(100) DEFAULT NULL,
          `annee` varchar(4) DEFAULT NULL,
          `categorie_2019` varchar(10) DEFAULT NULL,
          `nom` varchar(40) DEFAULT NULL,
          `prenom` varchar(40) DEFAULT NULL,
          `naissance` varchar(10) NOT NULL,
          `abandon` int(1) NOT NULL DEFAULT '0' COMMENT 'champs_abandon',
          `rgpd` int(1) NOT NULL DEFAULT '0',
          PRIMARY KEY (`id_dossard`)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1";
    }
}