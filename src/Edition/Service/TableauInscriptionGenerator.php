<?php

namespace App\Edition\Service;

use App\Edition\Model\EditionPayload;

/**
 * Génère courses/…/tableau_inscription_param.php
 */
final class TableauInscriptionGenerator
{
    private function q(string $value): string
    {
        return str_replace(["\\", "'"], ["\\\\", "\\'"], $value);
    }

    public function generate(EditionPayload $payload): string
    {
        $id = $payload->idHtml();
        $nomCourse = static function (int $i) use ($payload): string {
            foreach ($payload->courses as $course) {
                if ($course->index === $i) {
                    return $course->nomCourse;
                }
            }

            return '';
        };

        $heure = static fn (int $i): string => $payload->heuresDepart[$i - 1] ?? '';

        $lines = [];
        $lines[] = '<?php';
        $lines[] = '$tableau_inscription_param = array (';
        $lines[] = "\t\t'id_html'=>'".$this->q($id)."',";
        $lines[] = "\t\t'nom_evenement'=>'".$this->q($payload->eventName)."',";
        $lines[] = "\t\t'libelle_evenement_date'=>'".$this->q($payload->libelleDate)."',";
        $lines[] = "\t\t'date_evenement' => '".$this->q($payload->eventDate)."',";
        $lines[] = "\t\t'nom_fichier_affiche' => '".$this->q($payload->afficheFilename)."',";
        $lines[] = "\t\t'nom_fichier_affiche_tarif'=>'".$this->q($payload->afficheTarifFilename)."',";
        $lines[] = "\t\t'fichier_reglement' => '".$this->q($payload->reglementFilename)."',";
        $lines[] = "\t\t'contact_nom_organisateur' => '".$this->q($payload->contactNom)."',";
        $lines[] = "\t\t'contact_email_organisateur' => '".$this->q($payload->contactEmail)."',";
        $lines[] = "\t\t'contact_telephone_organisateur' => '".$this->q($payload->contactTel)."',";
        $lines[] = "\t\t'contact_lieu' => '".$this->q($payload->contactLieu)."',";
        $lines[] = "\t\t'site_inscription' =>'".$this->q($payload->siteInscription)."',";
        $lines[] = "\t\t'liste_inscription'=>'".$this->q($payload->listeInscription)."',";
        $lines[] = "\t\t'site_web_orga' => '".$this->q($payload->siteOrga)."',";
        $lines[] = "\t\t'site_web_nom' => '".$this->q($payload->siteOrgaNom)."',";
        $lines[] = "\t\t'site_web_orga2' => '',";
        $lines[] = "\t\t'site_web_nom2' => '',";
        $lines[] = "\t\t'site_web_orga3' => '',";
        $lines[] = "\t\t'site_web_nom3' => '',";
        $lines[] = "\t\t'texte_presentation' => '".$this->q($payload->textePresentation)."',";
        for ($i = 1; $i <= 6; ++$i) {
            $lines[] = "\t\t'nom_course_{$i}' => '".$this->q($nomCourse($i))."',";
        }
        $lines[] = "\t\t'table_dag_import' => '".$this->q($payload->tableDag)."',";
        $lines[] = "\t\t'table_run' => '".$this->q($payload->tableRun)."',";
        $lines[] = "\t\t'table_intermediaire' => '".$this->q($payload->tableIntermediaire)."',";
        $lines[] = "\t\t'categorie_ffa' => '".$this->q($payload->categorieFfa)."',";
        $lines[] = "\t\t'diplome' => '".$this->q($payload->diplome)."',";
        for ($i = 1; $i <= 6; ++$i) {
            $lines[] = "\t\t'heure_depart_course_{$i}' => '".$this->q($heure($i))."',";
        }
        $lines[] = "\t\t'classement_precedent' => '".$this->q($payload->classementPrecedent)."',";
        $lines[] = "\t\t'table_depart_tspreel' => '".$this->q($payload->tableDepartTspreel)."',";
        $lines[] = "\t\t'podium' => '".$payload->podium."',";
        $lines[] = "\t\t'limit' => '".$payload->podiumLimit."',";
        $lines[] = ');';
        $lines[] = '//$podium indique le nombre de récompense au scratch';
        $lines[] = '$podium='.$payload->podium.';';
        $lines[] = '//$limit indique le nombre de récompensé par categorie';
        $lines[] = '$limit='.$payload->podiumLimit.';';
        $lines[] = '';
        $lines[] = '// Métadonnées Symfony /rc (plages dossards)';
        $lines[] = '$RUNCHRONO_EDITION_META = '.json_encode(
            [
                'courses' => array_map(static fn ($c) => [
                    'index' => $c->index,
                    'nomCourse' => $c->nomCourse,
                    'distanceMetre' => $c->distanceMetre,
                    'startTimeDefaut' => $c->startTimeDefaut,
                    'nomExportOnsinscrit' => $c->nomExportOnsinscrit,
                    'dossardLabelHtml' => $c->dossardLabelHtml,
                    'bibRules' => array_map(static fn ($r) => [
                        'type' => $r->type,
                        'min' => $r->min,
                        'max' => $r->max,
                        'single' => $r->single,
                    ], $c->bibRules),
                ], $payload->courses),
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ).';';
        $lines[] = '';

        return implode("\n", $lines);
    }
}