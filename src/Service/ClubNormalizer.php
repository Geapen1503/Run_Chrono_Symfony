<?php

namespace App\Service;

use App\Edition\Support\LegacyStringHelper;

/**
 * Normalisation des noms, prénoms, licences et clubs selon les conventions RunChrono.
 * Équivalent modernisé et structuré par table de règles de includes/lib/fonction_fusionclub.php.
 */
final readonly class ClubNormalizer
{
    /**
     * Table ordonnée de correspondances regex → libellé canonique du club.
     * L'ordre reproduit les priorités historiques (notamment les motifs les plus précis en premier).
     *
     * @var list<array{pattern: string, target: string}>
     */
    private const REGLES_CLUBS = [
        ['pattern' => '/PICTAVE/', 'target' => 'CA PICTAVE'],
        ['pattern' => '/ACDC DESCARTES/', 'target' => 'ACDC DESCARTES'],
        ['pattern' => '/TRIATHLON CLUB CHATEL.*|TCC CHATEL.*|\bTCC\b/', 'target' => 'TRIATHLON CLUB CHATELLERAULT'],
        ['pattern' => '/COURIR DANS CHATELLERAULT.*|\bCDC\b/', 'target' => 'COURIR DANS CHATELLERAULT'],
        ['pattern' => '/ACC\s*37/', 'target' => 'ACC 37'],
        ['pattern' => '/US AI[GF]REFEUILLE ATHLETISME/', 'target' => 'US AIGREFEUILLE ATHLETISME'],
        ['pattern' => '/ASPRENAUT/', 'target' => 'ASPRENAUT CHALON SUR SAONE'],
        ['pattern' => '/AUCUN|SANS|NEANT|NO CLUB|PAS DE CLUB/', 'target' => ''],
        ['pattern' => '/\bBSN\b|BERUGES/', 'target' => 'BERUGES SPORT NATURE'],
        ['pattern' => '/CDC.*86/', 'target' => 'CDC 86'],
        ['pattern' => '/AIRVAUDAIS/', 'target' => 'FOOTING AIRVAUDAIS TRAIL'],
        ['pattern' => '/CELESTINES/', 'target' => 'FOULEES CELESTINES'],
        ['pattern' => '/LION\s*S CHATE.*|LIONS CHATE.*/', 'target' => 'LES LIONS CHATELLERAUDAIS'],
        ['pattern' => '/.*ULTRAMICAL.*|.*ULTR.*|.*AMICAL 86.*|.*ULTRA AMICAL.*/', 'target' => 'ULTRAMICAL86'],
        ['pattern' => '/.*TRAC.*ATHLE.*/', 'target' => 'TRACKS ATHLE'],
        ['pattern' => '/CLUB.*S.*T.*MAIXENT/', 'target' => 'RUNNING CLUB ST MAIXENT'],
        ['pattern' => '/.*GALOPINS .*BAIL.*/', 'target' => 'LES GALOPINS BAILLARGEOIS'],
        ['pattern' => '/.*GALOPINS .*BOIS.*/', 'target' => 'LES GALOPINS DES BOIS'],
        ['pattern' => '/FONDUS/', 'target' => 'LES FONDUS'],
        ['pattern' => '/.* CLOUERE/', 'target' => 'LES COMPAGNONS DE LA CLOUERE'],
        ['pattern' => '/.*CAVALEURS.*/', 'target' => 'LES CAVALEURS DU CLAIN'],
        ['pattern' => '/.*GATINE PARTHENAY.*|.*JOGGATINE.*|.*JOG GATINE.*/', 'target' => 'JOG GATINE PARTHENAY'],
        ['pattern' => '/GRAND ANGOULEME.*|\bG2A.*/', 'target' => 'G2A GRAND ANGOULEME'],
        ['pattern' => '/EPA86|\bEPA\b|ENTENTE POITIERS|POITIERS ATHLE 86/', 'target' => 'ENTENTE POITIERS ATHLE 86'],
        ['pattern' => '/VIVONNE/', 'target' => 'VIVONNE LOISIRS'],
        ['pattern' => '/VIENNE BIATHLON/', 'target' => 'VIENNE BIATHLON'],
        ['pattern' => '/VOUILLE/', 'target' => 'AS VOUILLE 86'],
        ['pattern' => '/USMA|US MIGNE/', 'target' => 'US MIGNE AUXANCES'],
        ['pattern' => '/SUAPS/', 'target' => 'SUAPS UNIV POITIERS'],
        ['pattern' => '/SP TRI POITIERS|POITEVIN TRI/', 'target' => 'STADE POITEVIN TRIATHLON'],
        ['pattern' => '/RUNARS|RUN ARS/', 'target' => 'RUN ARS'],
        ['pattern' => '/.*P.*T.* MOTEUR.*/', 'target' => 'LES PTITS MOTEURS'],
        ['pattern' => '/POITIERS COURSE ORIENTATION|POITIERS CO/', 'target' => 'POITIERS COURSE ORIENTATION'],
        ['pattern' => '/SPORTS? EN VILLE BUXEROLLES/', 'target' => 'SPORT EN VILLE BUXEROLLES'],
        ['pattern' => '/MILLE.*PAT.*BUX|(?:ASSOCIATION )?1000 PATTES (?:DE )?BUXEROLLES/', 'target' => 'MILLE PATTES DE BUXEROLLES'],
        ['pattern' => '/MAAC|MACC(?: LUSIGNAN)?/', 'target' => 'MACC LUSIGNAN'],
        ['pattern' => '/MISS ILE/', 'target' => 'MISS ILES'],
        ['pattern' => '/LJC(?: ?86| LENC.*)?|LENCLOITRE (?:J\.?C\.? ?86|JOGGING CLUB)/', 'target' => 'LENCLOITRE JC 86'],
        ['pattern' => '/NTR ?86/', 'target' => 'NTR 86'],
        ['pattern' => '/DOUCES/', 'target' => 'FOULEES DOUCES MONTAMISE'],
        ['pattern' => '/MONT.*RUN/', 'target' => 'MONT RUN MONTMORILLON'],
        ['pattern' => '/.*FOULEE.*POIT.*|TEAM LES FOULEES/', 'target' => 'TEAM FOULEES POITIERS'],
        ['pattern' => '/.*FOULE.*79/', 'target' => 'FOULEES 79'],
        ['pattern' => '/COURS TOUJOURS/', 'target' => 'COURS TOUJOURS LOUDUN'],
        ['pattern' => '/VAGABOND/', 'target' => 'COUREURS VAGABONDS'],
        ['pattern' => '/BIPEDE/', 'target' => 'BIPEDES DE SAINT-CYR'],
        ['pattern' => '/BALL?ADIN/', 'target' => 'BALADINS DE JARDRES'],
        ['pattern' => '/ASPP|AS POLICE/', 'target' => 'AS POLICE'],
        ['pattern' => '/RUN ?EVENT(?: ?86)?/', 'target' => 'RUN EVENT 86'],
        ['pattern' => '/.*GA.*ELLES.*FON.*|.*GA.*Z.*FON.*|LES GARS Z ELLES/', 'target' => 'LES GARS Z ELLES FONTENOISES'],
        ['pattern' => '/.*RUN.*IN VIGNE.*/', 'target' => 'RUN IN VIGNES'],
        ['pattern' => '/.*RUN.*VIGNE.*/', 'target' => 'LES RUNNEURS DES VIGNES'],
        ['pattern' => '/RUNIVERSITY/', 'target' => 'POITIERS RUNIVERSITY'],
        ['pattern' => '/TEAM.*TRAIL.*86/', 'target' => 'TEAM TRAIL 86'],
        ['pattern' => '/TEAM.*TRI.*86/', 'target' => 'TEAM TRI 86'],
        ['pattern' => '/TC VAL ?VERT/', 'target' => 'TENNIS CLUB VAL VERT'],
        ['pattern' => '/TC ?VAL/', 'target' => 'TC VAL'],
        ['pattern' => '/TRIATHLON.*CLUB.*CHATELLERAULT/', 'target' => 'TRIATHLON CLUB CHATELLERAULT'],
        ['pattern' => '/PLASSAC/', 'target' => 'CA PLASSAC'],
        ['pattern' => '/SBAC|SEVRE BOCAGE/', 'target' => 'SEVRE BOCAGE AC'],
        ['pattern' => '/CANIBALES.*79/', 'target' => 'CANIBALES 79'],
        ['pattern' => '/CANIKAZES.*86/', 'target' => 'CANIKAZES 86'],
        ['pattern' => '/.*TROTT.*PINAIL/', 'target' => 'LES TROTTEURS DU PINAIL'],
        ['pattern' => '/OUIRUN.*TEAM.*POITIERS/', 'target' => 'OUIRUN TEAM POITIERS'],
        ['pattern' => '/COD.*RUN/', 'target' => 'COD RUN'],
        ['pattern' => '/.*FOULL?EE ROCHELAISE/', 'target' => 'LA FOULEE ROCHELAISE'],
        ['pattern' => '/.*GAZELLES.*ANTRAN/', 'target' => 'LES GAZELLES ANTRAN'],
        ['pattern' => '/LA PRO ?TEAM/', 'target' => 'LA PRO TEAM'],
        ['pattern' => '/SNC86|SPORT NATURE CHAUVIGNY(?: 86)?/', 'target' => 'SPORT NATURE CHAUVIGNY'],
    ];

    /**
     * Nettoie un nom ou prénom : suppression des apostrophes et accents, compactage des espaces, majuscules.
     */
    public function normaliserIdentite(string $valeur): string
    {
        $sansApostrophe = str_replace("'", ' ', $valeur);
        $sansAccent = LegacyStringHelper::strToNoAccent($sansApostrophe);
        $compacte = preg_replace('/\s+/', ' ', trim($sansAccent)) ?? trim($sansAccent);

        return mb_strtoupper($compacte, 'UTF-8');
    }

    /**
     * Nettoie un numéro de licence FFA.
     */
    public function normaliserLicence(?string $licence): string
    {
        if ($licence === null || trim($licence) === '') {
            return '';
        }

        $sansApostrophe = str_replace("'", ' ', $licence);
        $sansAccent = LegacyStringHelper::strToNoAccent($sansApostrophe);

        return trim(preg_replace('/\s+/', ' ', $sansAccent) ?? $sansAccent);
    }

    /**
     * Normalise et fusionne un nom de club selon la table de référence RunChrono.
     */
    public function normaliserClub(?string $club): string
    {
        if ($club === null || trim($club) === '') {
            return '';
        }

        $nettoye = $this->normaliserIdentite($club);
        if ($nettoye === '') {
            return '';
        }

        foreach (self::REGLES_CLUBS as $regle) {
            if (preg_match($regle['pattern'], $nettoye) === 1) {
                return $regle['target'];
            }
        }

        return $nettoye;
    }
}
