<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Entrypoint;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EntrypointType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // --- Champs USER (non-mappés) ---
            ->add('totalPortfolio', NumberType::class, [
                'label' => 'Capital Total (€)',
                'mapped' => false,
                'data' => $options['user_data']?->getTotalPortfolio(),
                'attr' => ['placeholder' => 'Ex: 10000.00'],
                'scale' => 2,
            ])
            ->add('positionSize', NumberType::class, [
                'label' => 'Taille d\'une position (€)',
                'mapped' => false,
                'data' => $options['user_data']?->getPositionSize(),
                'attr' => ['placeholder' => 'Ex: 2000.00'],
                'scale' => 2,
            ])
            ->add('spread', ChoiceType::class, [
                'label' => 'Spread entre positions (%)',
                'mapped' => false,
                'data' => $options['user_data']?->getSpread() ?? 2,
                'choices' => [
                    '1 %' => 1,
                    '2 %' => 2,
                    '3 %' => 3,
                ],
                'expanded' => false, // Liste déroulante
                'multiple' => false,
            ])

            // --- Champ lié à l'ENTRYPOINT (mappé) ---
            ->add('entrypoint', NumberType::class, [
                'label' => 'Seuil d\'entrée (Points CAC)',
                'attr' => ['placeholder' => 'Ex: 7200.00'],
                'scale' => 2,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => Entrypoint::class,
                'user_data' => null, // On passera l'objet User ici depuis le controller
            ]
        );
    }
}
