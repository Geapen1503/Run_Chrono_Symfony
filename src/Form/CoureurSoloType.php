<?php

namespace App\Form;

use App\Model\CoureurSoloData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire d'inscription / modification d'un coureur solo sur place.
 * Équivalent modernisé du formulaire legacy dag/15_GET_run_form_ajout.php.
 */
class CoureurSoloType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $anneeCourante = (int) date('Y');
        $annees = [];
        for ($age = 3; $age <= 90; ++$age) {
            $anneeOption = $anneeCourante - $age;
            $annees[(string) $anneeOption] = $anneeOption;
        }

        /** @var list<string> $coursesDisponibles */
        $coursesDisponibles = $options['courses_disponibles'];
        $choicesCourses = [];
        foreach ($coursesDisponibles as $nomCourse) {
            $choicesCourses[$nomCourse] = $nomCourse;
        }

        $builder
            ->add('dossard', IntegerType::class, [
                'label' => 'Numéro de dossard',
                'attr' => [
                    'min' => 1,
                    'placeholder' => 'Ex. 342',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'maxlength' => 40,
                    'placeholder' => 'DUPONT',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr' => [
                    'maxlength' => 40,
                    'placeholder' => 'JEAN',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('sexe', ChoiceType::class, [
                'label' => 'Sexe',
                'placeholder' => 'Choisir…',
                'choices' => [
                    'Homme (H)' => 'H',
                    'Femme (F)' => 'F',
                ],
            ])
            ->add('annee', ChoiceType::class, [
                'label' => 'Année de naissance',
                'placeholder' => 'Choisir…',
                'choices' => $annees,
            ])
            ->add('club', TextType::class, [
                'label' => 'Club',
                'required' => false,
                'attr' => [
                    'maxlength' => 50,
                    'placeholder' => 'Facultatif (ex. EPA86)',
                    'list' => 'clubsExistantsDatalist',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('licence', TextType::class, [
                'label' => 'Numéro de licence FFA',
                'required' => false,
                'attr' => [
                    'maxlength' => 20,
                    'placeholder' => 'Facultatif',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('distanceManuelle', ChoiceType::class, [
                'label' => 'Course (si dossard hors plage codée)',
                'required' => false,
                'placeholder' => 'Détection automatique par plage de dossards',
                'choices' => $choicesCourses,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CoureurSoloData::class,
            'courses_disponibles' => [],
        ]);
        $resolver->setAllowedTypes('courses_disponibles', 'array');
    }
}
