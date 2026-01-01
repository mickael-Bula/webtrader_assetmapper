<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Entrypoint;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EntrypointType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('totalPortfolio', NumberType::class, [
                'label' => 'Capital Total dédié (€)',
                'attr' => ['placeholder' => 'Ex: 10000.00'],
                'html5' => true,
                'scale' => 2,
            ])
            ->add('entrypoint', NumberType::class, [
                'label' => 'Seuil d\'entrée (Points CAC)',
                'attr' => ['placeholder' => 'Ex: 7200.00'],
                'html5' => true,
                'scale' => 2,
            ])
            ->add('positionSize', NumberType::class, [
                'label' => 'Taille d\'une position (€)',
                'attr' => ['placeholder' => 'Ex: 2000.00'],
                'html5' => true,
                'scale' => 2,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Entrypoint::class]);
    }
}
