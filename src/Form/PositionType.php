<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Position;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PositionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('quantity', IntegerType::class, [
                'label' => 'Quantité',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: 100'],
            ])
            ->add('buyPrice', NumberType::class, [
                'label' => 'Prix Achat CAC',
                'scale' => 2,
                'attr' => ['placeholder' => 'Ex: 7200.00'],
            ])
            ->add('targetPrice', NumberType::class, [
                'label' => 'Objectif CAC',
                'scale' => 2,
                'attr' => ['placeholder' => 'Ex: 7920.00'],
            ])
            ->add('lvcBuyPrice', NumberType::class, [
                'label' => 'Prix Achat LVC (€)',
                'required' => false,
                'scale' => 2,
                'attr' => ['placeholder' => 'Ex: 12.50'],
            ])
            ->add('lvcTargetPrice', NumberType::class, [
                'label' => 'Objectif LVC (€)',
                'required' => false,
                'scale' => 2,
                'attr' => ['placeholder' => 'Ex: 15.00'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Position::class,
            'csrf_token_id' => 'position_edit',
        ]);
    }
}
