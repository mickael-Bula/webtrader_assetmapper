<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class PortfolioSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('buy_limit', NumberType::class, [
                'label' => "Limite d'achat",
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'step' => '0.01',
                ],
                'constraints' => [
                    new NotBlank(message: 'Veuillez renseigner la buy limit.'),
                    new Positive(message: 'La valeur doit être positive.'),
                ],
            ])
            ->add('total_allocation', NumberType::class, [
                'label' => 'Montant alloué au portefeuille',
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'step' => '0.01',
                ],
                'constraints' => [
                    new NotBlank(message: 'Veuillez renseigner le capital investi.'),
                    new Positive(message: 'Le montant doit être positif.'),
                ],
            ])
            ->add('position_size', NumberType::class, [
                'label' => 'Taille des positions',
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'step' => '0.01',
                ],
                'constraints' => [
                    new NotBlank(message: 'Veuillez définir une taille de position.'),
                    new Positive(message: 'La taille doit être positive.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
