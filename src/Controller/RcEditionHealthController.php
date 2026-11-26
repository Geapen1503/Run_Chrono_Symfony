<?php

namespace App\Controller;

use App\Edition\Service\LegacyPaths;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Diagnostic rapide pour aaPanel (erreurs 500 sur /rc/editions).
 */
#[Route('/rc/editions')]
#[IsGranted('ROLE_ADMIN')]
final class RcEditionHealthController extends AbstractController
{
    #[Route('/_health', name: 'rc_editions_health', methods: ['GET'])]
    public function health(
        LegacyPaths $paths,
        #[Autowire('%app.edition.includes_boilerplate%')]
        string $boilerplatePath,
        #[Autowire('%kernel.secret%')]
        string $appSecret,
    ): Response {
        $lines = [];
        $lines[] = 'APP_SECRET défini : '.($appSecret !== '' ? 'oui' : 'NON (obligatoire, sinon 500 sur le formulaire)');
        $legacy = $paths->legacyRoot();
        $lines[] = 'RUNCHRONO_LEGACY_ROOT : '.$legacy;
        $lines[] = 'Legacy root existe : '.(is_dir($legacy) ? 'oui' : 'NON');
        $lines[] = 'includes/ existe : '.(is_dir($legacy.DIRECTORY_SEPARATOR.'includes') ? 'oui' : 'non');
        $lines[] = 'courses/ existe : '.(is_dir($legacy.DIRECTORY_SEPARATOR.'courses') ? 'oui' : 'non');
        $lines[] = 'load_images : '.$paths->loadImagesDir();
        $lines[] = 'load_images existe : '.(is_dir($paths->loadImagesDir()) ? 'oui' : 'non');
        $lines[] = 'Boilerplate includes : '.$boilerplatePath;
        $lines[] = 'Boilerplate présent : '.(is_file($boilerplatePath) ? 'oui' : 'NON — fichier manquant au déploiement');

        return new Response('<pre>'.htmlspecialchars(implode("\n", $lines), \ENT_QUOTES, 'UTF-8').'</pre>');
    }
}