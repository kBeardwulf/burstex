<?php

namespace App\Form;

use App\Entity\Tournament;
use App\Entity\Town;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;

class TournamentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        ->add('name', TextType::class, [
                'label' => 'Nom de votre tournoi'
            ])
            ->add('discipline', TextType::class, [
                'label' => 'Jeu / Discipline'
            ])
            ->add('mail', EmailType::class, [
                'label' => 'Adresse e-mail de contact'
            ])
            ->add('date', null, [
                'label' => 'Date de début'
            ])
            ->add('end_date', DateType::class, [
                'label' => 'Date de fin'
            ])
            ->add('price', TextType::class, [
                'label' => 'Prix (laissez vide pour un tournoi gratuit!)',
                'required' => false,
            ])
            ->add('max_nb', TextType::class, [
                'label' => 'Nb de participants max (laissez vide pour sans limite!)',
                'required' => false,
            ])
            ->add('location', EntityType::class, [
                'class' => Town::class,
                'choice_label' => function(Town $town) {
                    return $town->getName() . ' (' . $town->getPostalCode() . ')';
                },
                'label' => 'Ville',
                'placeholder' => 'En ligne',
                'required' => false,
            ])
            ->add('address', TextType::class, [
                'label' => 'Addresse (laissez vide si en ligne!)',
                'required' => false,
            ]);

            if (!$options['is_edit']) {
                $builder
                ->add('agreeTerms', CheckboxType::class, [
                    'mapped'        => false,
                    'label'         => "J'accepter d'adhérer au règlement du tournoi",
                    'constraints'   => [ new IsTrue(message: 'Vous devez accepter les conditions')],
                ]);
            }
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tournament::class,
            'is_edit' => false,
        ]);
    }
}