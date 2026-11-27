<?php

namespace App\Service;

/**
 * Calcul des catégories officielles FFA (grille 2020 et grille regroupée 2019).
 * Équivalent modernisé de includes/lib/fonctions_categorie.php.
 */
final readonly class CategorieFfaCalculator
{
    /**
     * Paliers d'âge minimum → code catégorie FFA (du plus âgé au plus jeune).
     *
     * @var list<array{int, string}>
     */
    private const PALIERS_FFA_2020 = [
        [85, 'M10'],
        [80, 'M9'],
        [75, 'M8'],
        [70, 'M7'],
        [65, 'M6'],
        [60, 'M5'],
        [55, 'M4'],
        [50, 'M3'],
        [45, 'M2'],
        [40, 'M1'],
        [35, 'M0'],
        [23, 'SE'],
        [20, 'ES'],
        [18, 'JU'],
        [16, 'CA'],
        [14, 'MI'],
        [12, 'BE'],
        [10, 'PO'],
        [7, 'EA'],
    ];

    /**
     * Correspondance grille 2020 → grille regroupée 2019.
     *
     * @var array<string, string>
     */
    private const REGROUPEMENTS_2019 = [
        'SE' => 'SE/M0',
        'M0' => 'SE/M0',
        'M1' => 'M1/M2',
        'M2' => 'M1/M2',
        'M3' => 'M3/M4',
        'M4' => 'M3/M4',
        'M5' => 'M5/M6',
        'M6' => 'M5/M6',
        'M7' => 'M7/M8',
        'M8' => 'M7/M8',
        'M9' => 'M9/M10',
        'M10' => 'M9/M10',
    ];

    /**
     * Calcule l'année de référence de la saison sportive FFA (bascule après le 1er septembre).
     */
    public function getAnneeSaisonFfa(?\DateTimeInterface $dateReference = null): int
    {
        $date = $dateReference ?? new \DateTimeImmutable('today');
        $annee = (int) $date->format('Y');
        $bascule = new \DateTimeImmutable(sprintf('%d-09-01', $annee));

        if ($date > $bascule) {
            return $annee + 1;
        }

        return $annee;
    }

    /**
     * Calcule le code catégorie FFA 2020 (sans suffixe sexe) à partir de l'année de naissance.
     */
    public function calculerCategorie2020(int $anneeNaissance, ?\DateTimeInterface $dateReference = null): string
    {
        $age = $this->getAnneeSaisonFfa($dateReference) - $anneeNaissance;

        foreach (self::PALIERS_FFA_2020 as [$ageMin, $code]) {
            if ($age >= $ageMin) {
                return $code;
            }
        }

        return 'BB';
    }

    /**
     * Calcule le code catégorie regroupé (grille 2019, sans suffixe sexe).
     */
    public function calculerCategorie2019(int $anneeNaissance, ?\DateTimeInterface $dateReference = null): string
    {
        $categorie2020 = $this->calculerCategorie2020($anneeNaissance, $dateReference);

        return self::REGROUPEMENTS_2019[$categorie2020] ?? $categorie2020;
    }

    /**
     * Retourne les deux catégories formatées avec le sexe (ex. "SE-H" et "SE/M0-H").
     *
     * @return array{categorie: string, categorie_2019: string}
     */
    public function calculerAvecSexe(
        int $anneeNaissance,
        string $sexe,
        ?\DateTimeInterface $dateReference = null,
    ): array {
        $sexeNormalise = strtoupper(trim($sexe));
        $cat2020 = $this->calculerCategorie2020($anneeNaissance, $dateReference);
        $cat2019 = self::REGROUPEMENTS_2019[$cat2020] ?? $cat2020;

        return [
            'categorie' => sprintf('%s-%s', $cat2020, $sexeNormalise),
            'categorie_2019' => sprintf('%s-%s', $cat2019, $sexeNormalise),
        ];
    }
}
