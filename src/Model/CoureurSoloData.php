<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO du formulaire d'ajout / modification d'un coureur solo (console live /rc).
 */
class CoureurSoloData
{
    #[Assert\NotBlank(message: 'Le numéro de dossard est obligatoire.')]
    #[Assert\Positive(message: 'Le numéro de dossard doit être un entier positif.')]
    #[Assert\LessThanOrEqual(value: 999999, message: 'Le numéro de dossard est trop grand.')]
    public ?int $dossard = null;

    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(max: 40, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.')]
    public string $nom = '';

    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(max: 40, maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.')]
    public string $prenom = '';

    #[Assert\NotBlank(message: 'Le sexe est obligatoire.')]
    #[Assert\Choice(choices: ['H', 'F'], message: 'Le sexe doit être Homme (H) ou Femme (F).')]
    public string $sexe = '';

    #[Assert\NotBlank(message: "L'année de naissance est obligatoire.")]
    #[Assert\Range(min: 1920, max: 2026, notInRangeMessage: "L'année de naissance doit être comprise entre {{ min }} et {{ max }}.")]
    public ?int $annee = null;

    #[Assert\Length(max: 50, maxMessage: 'Le club ne peut pas dépasser {{ limit }} caractères.')]
    public ?string $club = null;

    #[Assert\Length(max: 20, maxMessage: 'La licence ne peut pas dépasser {{ limit }} caractères.')]
    public ?string $licence = null;

    /**
     * Course de repli sélectionnable uniquement si le dossard n'est couvert par aucune plage codée.
     */
    #[Assert\Length(max: 100)]
    public ?string $distanceManuelle = null;
}
