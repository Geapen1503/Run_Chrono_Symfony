<?php

namespace App\Edition\Service;

use App\Edition\Model\EditionBibRule;
use App\Edition\Model\EditionCourseConfig;
use App\Edition\Model\EditionPayload;
use App\Edition\Support\LegacyStringHelper;
use Symfony\Component\HttpFoundation\Request;

/**
 * Construit EditionPayload depuis la requête HTTP (formulaire /rc/editions).
 */
final class EditionPayloadFactory
{
    public function fromRequest(Request $request): EditionPayload
    {
        $payload = new EditionPayload(
            eventDate: (string) $request->request->get('event_date', ''),
            slug: LegacyStringHelper::slugify((string) $request->request->get('slug', '')),
            eventName: (string) $request->request->get('event_name', ''),
            libelleDate: (string) $request->request->get('libelle_date', ''),
            textePresentation: (string) $request->request->get('texte_presentation', ''),
            siteInscription: (string) $request->request->get('site_inscription', ''),
            contactNom: (string) $request->request->get('contact_nom', ''),
            contactEmail: (string) $request->request->get('contact_email', ''),
            contactTel: (string) $request->request->get('contact_tel', ''),
            contactLieu: (string) $request->request->get('contact_lieu', ''),
            siteOrga: (string) $request->request->get('site_orga', ''),
            siteOrgaNom: (string) $request->request->get('site_orga_nom', ''),
            afficheFilename: (string) $request->request->get('affiche_filename', ''),
            afficheTarifFilename: (string) $request->request->get('affiche_tarif_filename', ''),
            reglementFilename: (string) $request->request->get('reglement_filename', ''),
            listeInscription: (string) $request->request->get('liste_inscription', ''),
            categorieFfa: (string) $request->request->get('categorie_ffa', 'ffa_2020_q'),
            tableRun: (string) $request->request->get('table_run', ''),
            tableDag: (string) $request->request->get('table_dag', ''),
            tableIntermediaire: (string) $request->request->get('table_intermediaire', ''),
            tableDepartTspreel: (string) $request->request->get('table_depart_tspreel', ''),
            classementPrecedent: (string) $request->request->get('classement_precedent', ''),
            podium: (int) $request->request->get('podium', 3),
            podiumLimit: (int) $request->request->get('podium_limit', 1),
            diplome: (string) $request->request->get('diplome', ''),
            heuresDepart: [
                (string) $request->request->get('heure_depart_1', ''),
                (string) $request->request->get('heure_depart_2', ''),
                (string) $request->request->get('heure_depart_3', ''),
                (string) $request->request->get('heure_depart_4', ''),
                (string) $request->request->get('heure_depart_5', ''),
                (string) $request->request->get('heure_depart_6', ''),
            ],
            createMysqlTables: $request->request->getBoolean('create_mysql_tables'),
            syncCatalog: $request->request->getBoolean('sync_catalog', true),
            writeLegacyFiles: $request->request->getBoolean('write_legacy_files', true),
            setActiveEdition: $request->request->getBoolean('set_active_edition'),
        );

        $courses = [];
        $rawCourses = $request->request->all('courses');
        if (\is_array($rawCourses)) {
            foreach ($rawCourses as $index => $data) {
                if (!\is_array($data)) {
                    continue;
                }
                $idx = (int) $index;
                $rules = [];
                $rawRules = $data['bib_rules'] ?? [];
                if (\is_array($rawRules)) {
                    foreach ($rawRules as $rule) {
                        if (!\is_array($rule)) {
                            continue;
                        }
                        $type = (string) ($rule['type'] ?? 'range');
                        $rules[] = new EditionBibRule(
                            type: $type,
                            min: ($rule['min'] ?? '') !== '' ? (int) $rule['min'] : null,
                            max: ($rule['max'] ?? '') !== '' ? (int) $rule['max'] : null,
                            single: ($rule['single'] ?? '') !== '' ? (int) $rule['single'] : null,
                        );
                    }
                }
                $courses[] = new EditionCourseConfig(
                    index: $idx,
                    nomCourse: (string) ($data['nom_course'] ?? ''),
                    distanceMetre: (string) ($data['distance_metre'] ?? ''),
                    startTimeDefaut: (string) ($data['start_time_defaut'] ?? '09:00:00'),
                    nomExportOnsinscrit: (string) ($data['nom_export_onsinscrit'] ?? ''),
                    dossardLabelHtml: (string) ($data['dossard_label_html'] ?? ''),
                    bibRules: $rules,
                );
            }
        } else {
            for ($i = 1; $i <= 6; ++$i) {
                $courses[] = new EditionCourseConfig(index: $i);
            }
        }
        usort($courses, static fn ($a, $b) => $a->index <=> $b->index);
        $payload->courses = $courses;

        return $payload;
    }
}