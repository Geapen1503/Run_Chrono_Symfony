<?php

namespace App\Edition\Service;

use App\Edition\Model\EditionPayload;

/**
 * Orchestration enregistrement édition (fichiers + MySQL + activation).
 */
final class EditionWorkflow
{
    public function __construct(
        private EditionNamingService $naming,
        private EditionFilePublisher $publisher,
        private EditionTableProvisioner $tableProvisioner,
        private ParamInscriptionCatalogSync $catalogSync,
        private ParametreCourseActivator $activator,
        private ActiveEditionStore $activeEditionStore,
    ) {
    }

    /**
     * @return array{warnings: list<string>, messages: list<string>}
     */
    public function save(EditionPayload $payload): array
    {
        $this->naming->assertSafeIdHtml($payload->idHtml());
        $payload = $this->naming->buildFromPayload($payload);

        $warnings = [];
        $messages = [];

        if ($payload->writeLegacyFiles) {
            $this->publisher->publish($payload);
            $messages[] = 'Fichiers legacy générés (courses/ + includes/).';
        }

        if ($payload->createMysqlTables) {
            $result = $this->tableProvisioner->provision(
                $payload->tableRun,
                $payload->tableDag,
                $payload->year(),
            );
            $warnings = array_merge($warnings, $result['warnings']);
            $messages[] = 'Tables MySQL run_* / dag_import_* vérifiées.';
        }

        if ($payload->syncCatalog) {
            $this->catalogSync->sync($payload);
            $messages[] = 'Catalogue param_inscription_manuelle synchronisé.';
        }

        if ($payload->setActiveEdition) {
            $this->activator->activate($payload->idHtml());
            $this->activeEditionStore->set($payload->idHtml());
            $messages[] = 'Édition active (parametre_course.php + contexte /rc).';
        }

        return ['warnings' => $warnings, 'messages' => $messages];
    }
}