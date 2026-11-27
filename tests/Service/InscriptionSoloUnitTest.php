<?php

namespace App\Tests\Service;

use App\Edition\Service\EditionCatalogReader;
use App\Edition\Service\LegacyPaths;
use App\Service\CategorieFfaCalculator;
use App\Service\ClubNormalizer;
use App\Service\DossardResolverService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

class InscriptionSoloUnitTest extends TestCase
{
    public function testCalculCategoriesFfaAvantEtApresBasculeSeptembre(): void
    {
        $calc = new CategorieFfaCalculator();

        // Saison 2026 (avant le 1er septembre 2026) : né en 2000 => 26 ans => SE
        $avantBascule = new \DateTimeImmutable('2026-06-15');
        $this->assertSame('SE', $calc->calculerCategorie2020(2000, $avantBascule));
        $this->assertSame('SE/M0', $calc->calculerCategorie2019(2000, $avantBascule));

        // Saison 2027 (après le 1er septembre 2026) : né en 1992 => 2027 - 1992 = 35 ans => M0
        $apresBascule = new \DateTimeImmutable('2026-11-26');
        $res = $calc->calculerAvecSexe(1992, 'f', $apresBascule);
        $this->assertSame('M0-F', $res['categorie']);
        $this->assertSame('SE/M0-F', $res['categorie_2019']);

        // Baby Athlé (< 7 ans)
        $this->assertSame('BB', $calc->calculerCategorie2020(2022, $avantBascule));
    }

    public function testNormalisationIdentiteLicenceEtClubs(): void
    {
        $normalizer = new ClubNormalizer();

        $this->assertSame('D ARTAGNAN', $normalizer->normaliserIdentite(" d'Artagnan  "));
        $this->assertSame('STEPHANE', $normalizer->normaliserIdentite('Stéphane'));
        $this->assertSame('1659493', $normalizer->normaliserLicence(' 1659493 '));

        $this->assertSame('ENTENTE POITIERS ATHLE 86', $normalizer->normaliserClub('EPA86'));
        $this->assertSame('CA PICTAVE', $normalizer->normaliserClub('C.A. Pictave'));
        $this->assertSame('', $normalizer->normaliserClub('Aucun club'));
        $this->assertSame('MON CLUB PERSO', $normalizer->normaliserClub('Mon Club Perso'));
    }

    public function testResolutionDossardsDepuisFichierIncludesLegacy(): void
    {
        $tmpDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rc_test_'.uniqid('', true);
        mkdir($tmpDir.DIRECTORY_SEPARATOR.'includes', 0777, true);

        $idHtml = '20260919_Test_Course';
        $phpContent = <<<'PHP'
<?php
function tab_param($num_dossard){
    $tab_param_course_1 = array(
        'nom_course' => 'Course 6KM',
        'distance_metre' => '6000',
    );
    $tab_param_course_2 = array(
        'nom_course' => 'Trail 11 KM',
        'distance_metre' => '11400',
    );
    /*if ($num_dossard==353) { return $tab_param_course_2; } */
    if ($num_dossard==818) { return $tab_param_course_1; }
    if ($num_dossard>=336 AND $num_dossard<=485) { return $tab_param_course_1; }
    elseif ($num_dossard>=787 AND $num_dossard<=936) { return $tab_param_course_2; }
    elseif ($num_dossard==-1) { return $tab_param_course_1; }
    else return $tab_param_else;
}
PHP;
        file_put_contents($tmpDir.DIRECTORY_SEPARATOR.'includes'.DIRECTORY_SEPARATOR.$idHtml.'.php', $phpContent);

        try {
            $paths = new LegacyPaths($tmpDir, $tmpDir, 'courses');
            $connection = $this->createStub(Connection::class);
            $catalogReader = new EditionCatalogReader($connection, $paths);
            $resolver = new DossardResolverService($catalogReader, $paths);

            $evenement = [
                'id_html' => $idHtml,
                'nom_course_1' => 'Course 6KM',
                'nom_course_2' => 'Trail 11 KM',
            ];

            $this->assertTrue($resolver->possedeReglesDossards($evenement));
            $this->assertSame('Course 6KM', $resolver->resoudreCoursePourDossard($evenement, 340));
            // Exception unitaire prioritaire sur la plage [787..936]
            $this->assertSame('Course 6KM', $resolver->resoudreCoursePourDossard($evenement, 818));
            // Le commentaire /* if ($num_dossard==353) ... */ est bien ignoré
            $this->assertSame('Course 6KM', $resolver->resoudreCoursePourDossard($evenement, 353));
            $this->assertSame('Trail 11 KM', $resolver->resoudreCoursePourDossard($evenement, 800));
            $this->assertNull($resolver->resoudreCoursePourDossard($evenement, 9999));

            $stats = $resolver->calculerStatsDossards($evenement, [
                ['id_dossard' => 1, 'dossard_num' => 336, 'distance' => 'Course 6KM'],
                ['id_dossard' => 2, 'dossard_num' => 337, 'distance' => 'Course 6KM'],
            ]);
            $this->assertCount(2, $stats);
            $this->assertSame(338, $stats[0]['prochain_dossard']);
            $this->assertSame(787, $stats[1]['prochain_dossard']);
        } finally {
            @unlink($tmpDir.DIRECTORY_SEPARATOR.'includes'.DIRECTORY_SEPARATOR.$idHtml.'.php');
            @rmdir($tmpDir.DIRECTORY_SEPARATOR.'includes');
            @rmdir($tmpDir);
        }
    }
}
