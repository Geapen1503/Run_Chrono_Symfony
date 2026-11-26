<?php

namespace App\Edition\Model;

/**
 * Données saisies pour créer / modifier une édition (étapes 2+5 legacy).
 */
final class EditionPayload
{
    public function __construct(
        public string $eventDate = '',
        public string $slug = '',
        public string $eventName = '',
        public string $libelleDate = '',
        public string $textePresentation = '',
        public string $siteInscription = '',
        public string $contactNom = '',
        public string $contactEmail = '',
        public string $contactTel = '',
        public string $contactLieu = '',
        public string $siteOrga = '',
        public string $siteOrgaNom = '',
        public string $afficheFilename = '',
        public string $afficheTarifFilename = '',
        public string $reglementFilename = '',
        public string $listeInscription = '',
        public string $categorieFfa = 'ffa_2020_q',
        public string $tableRun = '',
        public string $tableDag = '',
        public string $tableIntermediaire = '',
        public string $tableDepartTspreel = '',
        public string $classementPrecedent = '',
        public int $podium = 3,
        public int $podiumLimit = 1,
        public string $diplome = '',
        public array $heuresDepart = ['', '', '', '', '', ''],
        /** @var list<EditionCourseConfig> */
        public array $courses = [],
        public bool $createMysqlTables = true,
        public bool $syncCatalog = true,
        public bool $writeLegacyFiles = true,
        public bool $setActiveEdition = false,
    ) {
    }

    public function year(): string
    {
        return substr($this->eventDate, 0, 4);
    }

    public function idHtml(): string
    {
        return str_replace('-', '', $this->eventDate).'_'.$this->slug;
    }
}