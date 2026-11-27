<?php

namespace App\Service;

use App\Edition\Model\EditionBibRule;
use App\Edition\Model\EditionCourseConfig;
use App\Edition\Service\EditionCatalogReader;
use App\Edition\Service\LegacyPaths;

/**
 * Résolution des plages de dossards → course (distance) et calcul des disponibilités par épreuve.
 * Supporte à la fois les éditions Symfony ($RUNCHRONO_EDITION_META) et les fichiers legacy includes/{id_html}.php.
 */
final class DossardResolverService
{
    public const COURSE_NON_CODE = 'COURSE_NON_CODE';

    public function __construct(
        private readonly EditionCatalogReader $catalogReader,
        private readonly LegacyPaths $legacyPaths,
    ) {
    }

    /**
     * Retourne les configurations de courses (avec leurs règles de dossards si disponibles) pour un événement.
     *
     * @param array<string, mixed> $evenement
     * @return list<EditionCourseConfig>
     */
    public function getCoursesConfig(array $evenement): array
    {
        $idHtml = trim((string) ($evenement['id_html'] ?? ''));

        // 1. Tentative via EditionCatalogReader (éditions avec métadonnées JSON dans tableau_inscription_param.php)
        if ($idHtml !== '' && preg_match('/^20\d{6}_[A-Za-z0-9_]+$/', $idHtml) === 1) {
            try {
                $payload = $this->catalogReader->loadForEdit($idHtml);
                $coursesAvecRegles = array_values(array_filter(
                    $payload->courses,
                    static fn (EditionCourseConfig $c): bool => $c->isUsed() && $c->bibRules !== []
                ));
                if ($coursesAvecRegles !== []) {
                    return array_values(array_filter(
                        $payload->courses,
                        static fn (EditionCourseConfig $c): bool => $c->isUsed()
                    ));
                }
            } catch (\Throwable) {
                // On bascule sur le parseur statique du fichier legacy includes/{id_html}.php
            }

            // 2. Fallback lecture statique sécurisée (sans include/eval) du fichier includes/{id_html}.php
            $includesPath = $this->legacyPaths->includesPath($idHtml);
            if (is_file($includesPath)) {
                $parsed = $this->parserIncludesLegacy($includesPath, $evenement);
                if ($parsed !== []) {
                    return $parsed;
                }
            }
        }

        // 3. Fallback minimal depuis les colonnes nom_course_1..6 de param_inscription_manuelle
        return $this->construireDepuisEvenement($evenement);
    }

    /**
     * Indique si l'événement possède au moins une règle d'attribution dossard → course.
     *
     * @param array<string, mixed> $evenement
     */
    public function possedeReglesDossards(array $evenement): bool
    {
        foreach ($this->getCoursesConfig($evenement) as $course) {
            if ($course->bibRules !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Détermine le nom de la course associé à un numéro de dossard, ou null si hors plage.
     * Respecte l'ordre de priorité exact : règles unitaires (single) avant les plages (range), ou premier match.
     *
     * @param array<string, mixed> $evenement
     */
    public function resoudreCoursePourDossard(array $evenement, int $dossard): ?string
    {
        if ($dossard <= 0) {
            return null;
        }

        $courses = $this->getCoursesConfig($evenement);

        // Priorité aux exceptions unitaires ($num_dossard == X), comme dans tab_param()
        foreach ($courses as $course) {
            foreach ($course->bibRules as $rule) {
                if ($rule->type === 'single' && $rule->single === $dossard) {
                    return $course->nomCourse;
                }
            }
        }

        // Puis correspondance sur les plages [min..max]
        foreach ($courses as $course) {
            foreach ($course->bibRules as $rule) {
                if (
                    $rule->type === 'range'
                    && $rule->min !== null
                    && $rule->max !== null
                    && $dossard >= $rule->min
                    && $dossard <= $rule->max
                ) {
                    return $course->nomCourse;
                }
            }
        }

        return null;
    }

    /**
     * Calcule pour chaque course active : plages codées, dossards attribués, restants et prochain dossard libre.
     * Équivalent modernisé de dag/tablette/stat_5_table_run_max_dossard.php.
     *
     * @param array<string, mixed> $evenement
     * @param list<array<string, mixed>> $participants
     * @return list<array<string, mixed>>
     */
    public function calculerStatsDossards(array $evenement, array $participants): array
    {
        $courses = $this->getCoursesConfig($evenement);

        // Indexation des dossards déjà utilisés globalement et par course
        $dossardsUtilisesGlobal = [];
        $participantsParCourse = [];
        foreach ($participants as $participant) {
            $dossardNum = (int) ($participant['dossard_num'] ?? 0);
            if ($dossardNum > 0) {
                $dossardsUtilisesGlobal[$dossardNum] = true;
            }
            $distance = trim((string) ($participant['distance'] ?? ''));
            if ($distance !== '') {
                $participantsParCourse[$distance][] = $participant;
            }
        }

        $stats = [];
        foreach ($courses as $course) {
            $inscrits = $participantsParCourse[$course->nomCourse] ?? [];
            $codes = $this->extraireNumerosCodes($course->bibRules);
            $totalCodes = count($codes);

            $maxDossardCourse = 0;
            $dossardDernierId = 0;
            $maxId = -1;
            foreach ($inscrits as $p) {
                $num = (int) ($p['dossard_num'] ?? 0);
                $idDossard = (int) ($p['id_dossard'] ?? 0);
                if ($num > $maxDossardCourse) {
                    $maxDossardCourse = $num;
                }
                if ($idDossard >= $maxId && $num > 0) {
                    $maxId = $idDossard;
                    $dossardDernierId = $num;
                }
            }

            $prochainDossard = $this->calculerProchainDossardLibre(
                $codes,
                $dossardsUtilisesGlobal,
                $dossardDernierId > 0 ? $dossardDernierId : $maxDossardCourse
            );

            $attribues = count($inscrits);
            $restants = $totalCodes > 0 ? max(0, $totalCodes - $attribues) : null;

            $stats[] = [
                'index' => $course->index,
                'nom' => $course->nomCourse,
                'heure_depart' => $evenement["heure_depart_course_{$course->index}"] ?? ($course->startTimeDefaut ?: null),
                'plages_label' => $this->formaterPlages($course->bibRules),
                'a_regles' => $course->bibRules !== [],
                'total_codes' => $totalCodes,
                'attribues' => $attribues,
                'restants' => $restants,
                'complet' => $restants !== null && $restants === 0,
                'prochain_dossard' => $prochainDossard,
            ];
        }

        return $stats;
    }

    /**
     * Parse statiquement la fonction tab_param() d'un fichier legacy includes/{id_html}.php.
     *
     * @param array<string, mixed> $evenement
     * @return list<EditionCourseConfig>
     */
    private function parserIncludesLegacy(string $path, array $evenement): array
    {
        $contenu = @file_get_contents($path);
        if ($contenu === false || $contenu === '') {
            return [];
        }

        // Suppression des commentaires /* ... */ et // ... pour ignorer d'anciennes règles désactivées
        $sansCommentaires = preg_replace('!/\*.*?\*/!s', '', $contenu) ?? $contenu;
        $sansCommentaires = preg_replace('/\/\/[^\r\n]*/', '', $sansCommentaires) ?? $sansCommentaires;

        // Extraction des noms de courses par index ($tab_param_course_1..6)
        $coursesParIndex = [];
        if (preg_match_all(
            '/\$tab_param_course_(\d+)\s*=\s*array\s*\((.*?)\)\s*;/s',
            $sansCommentaires,
            $blocs,
            \PREG_SET_ORDER
        )) {
            foreach ($blocs as $bloc) {
                $idx = (int) $bloc[1];
                if ($idx < 1 || $idx > 6) {
                    continue;
                }
                if (preg_match("/'nom_course'\s*=>\s*'((?:\\\\'|[^'])*)'/", $bloc[2], $nomMatch)) {
                    $nomCourse = stripcslashes($nomMatch[1]);
                    if ($nomCourse !== '') {
                        $coursesParIndex[$idx] = new EditionCourseConfig(
                            index: $idx,
                            nomCourse: $nomCourse,
                            startTimeDefaut: (string) ($evenement["heure_depart_course_{$idx}"] ?? '09:00:00'),
                        );
                    }
                }
            }
        }

        if ($coursesParIndex === []) {
            return [];
        }

        // Extraction des plages ($num_dossard >= A AND $num_dossard <= B)
        if (preg_match_all(
            '/\$num_dossard\s*>=\s*(\d+)\s*(?:AND|&&)\s*\$num_dossard\s*<=\s*(\d+)\s*\)\s*\{\s*return\s*\$tab_param_course_(\d+)\s*;/i',
            $sansCommentaires,
            $plages,
            \PREG_SET_ORDER
        )) {
            foreach ($plages as $match) {
                $min = (int) $match[1];
                $max = (int) $match[2];
                $idx = (int) $match[3];
                if (isset($coursesParIndex[$idx]) && $min > 0 && $max >= $min) {
                    $coursesParIndex[$idx]->bibRules[] = new EditionBibRule(
                        type: 'range',
                        min: $min,
                        max: $max,
                    );
                }
            }
        }

        // Extraction des numéros unitaires positifs ($num_dossard == X)
        if (preg_match_all(
            '/\$num_dossard\s*==\s*(\d+)\s*\)\s*\{\s*return\s*\$tab_param_course_(\d+)\s*;/i',
            $sansCommentaires,
            $unitaires,
            \PREG_SET_ORDER
        )) {
            foreach ($unitaires as $match) {
                $num = (int) $match[1];
                $idx = (int) $match[2];
                if (isset($coursesParIndex[$idx]) && $num > 0) {
                    $coursesParIndex[$idx]->bibRules[] = new EditionBibRule(
                        type: 'single',
                        single: $num,
                    );
                }
            }
        }

        ksort($coursesParIndex);

        return array_values($coursesParIndex);
    }

    /**
     * @param array<string, mixed> $evenement
     * @return list<EditionCourseConfig>
     */
    private function construireDepuisEvenement(array $evenement): array
    {
        $courses = [];
        for ($i = 1; $i <= 6; ++$i) {
            $nom = trim((string) ($evenement["nom_course_{$i}"] ?? ''));
            if ($nom !== '') {
                $courses[] = new EditionCourseConfig(
                    index: $i,
                    nomCourse: $nom,
                    startTimeDefaut: (string) ($evenement["heure_depart_course_{$i}"] ?? '09:00:00'),
                );
            }
        }

        return $courses;
    }

    /**
     * @param list<EditionBibRule> $rules
     * @return list<int>
     */
    private function extraireNumerosCodes(array $rules): array
    {
        $numeros = [];
        foreach ($rules as $rule) {
            if ($rule->type === 'single' && $rule->single !== null && $rule->single > 0) {
                $numeros[$rule->single] = true;
            } elseif ($rule->type === 'range' && $rule->min !== null && $rule->max !== null && $rule->max >= $rule->min) {
                // Garde-fou mémoire sur une plage raisonnable
                $maxBorne = min($rule->max, $rule->min + 20000);
                for ($n = $rule->min; $n <= $maxBorne; ++$n) {
                    $numeros[$n] = true;
                }
            }
        }

        $liste = array_keys($numeros);
        sort($liste);

        return $liste;
    }

    /**
     * Suggère le prochain dossard disponible pour les inscriptions sur place :
     * - Si la course possède des numéros codés, privilégie (dernier dossard + 1) s'il est codé et libre,
     *   sinon le premier numéro codé libre.
     * - Sans règle codée, retourne dernier dossard + 1 si connu.
     *
     * @param list<int> $codes
     * @param array<int, true> $dossardsUtilisesGlobal
     */
    private function calculerProchainDossardLibre(
        array $codes,
        array $dossardsUtilisesGlobal,
        int $dernierDossardCourse,
    ): ?int {
        if ($codes === []) {
            if ($dernierDossardCourse <= 0) {
                return null;
            }
            $candidat = $dernierDossardCourse + 1;
            while (isset($dossardsUtilisesGlobal[$candidat])) {
                ++$candidat;
            }

            return $candidat;
        }

        $codesMap = array_flip($codes);
        if ($dernierDossardCourse > 0) {
            $candidat = $dernierDossardCourse + 1;
            if (isset($codesMap[$candidat]) && !isset($dossardsUtilisesGlobal[$candidat])) {
                return $candidat;
            }
        }

        // Recherche du prochain numéro libre après le dernier dossard utilisé dans les plages, puis repli sur le premier libre
        foreach ($codes as $num) {
            if ($num > $dernierDossardCourse && !isset($dossardsUtilisesGlobal[$num])) {
                return $num;
            }
        }
        foreach ($codes as $num) {
            if (!isset($dossardsUtilisesGlobal[$num])) {
                return $num;
            }
        }

        return null;
    }

    /**
     * @param list<EditionBibRule> $rules
     */
    private function formaterPlages(array $rules): string
    {
        if ($rules === []) {
            return 'Non codée';
        }

        $segments = [];
        foreach ($rules as $rule) {
            if ($rule->type === 'range' && $rule->min !== null && $rule->max !== null) {
                $segments[] = sprintf('[%d–%d]', $rule->min, $rule->max);
            } elseif ($rule->type === 'single' && $rule->single !== null) {
                $segments[] = sprintf('#%d', $rule->single);
            }
        }

        return implode(', ', $segments);
    }
}
