<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Position;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PositionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // On définit des options communes pour éviter la répétition
        $inputClass = 'form-control form-control-sm bg-black text-white border-secondary';
        $labelClass = 'x-small text-secondary d-block mb-1';

        // On récupère le contrôleur s'il est défini
        $controller = $options['stimulus_controller'] ?? null;

        $builder
            ->add('buyPrice', NumberType::class, [
                'label' => 'Prix Achat CAC',
                'scale' => 2,
                'label_attr' => ['class' => $labelClass],
                'attr' => $this->getAttr($controller, ['class' => $inputClass, 'placeholder' => '7200.00'], 'buyCac', true),
                'row_attr' => ['class' => 'mb-2'],
            ])
            ->add('targetPrice', NumberType::class, [
                'label' => 'Objectif CAC',
                'scale' => 2,
                'label_attr' => ['class' => $labelClass],
                'attr' => $this->getAttr($controller, ['class' => $inputClass, 'placeholder' => '7920.00'], 'targetCac'),
                'row_attr' => ['class' => 'mb-2'],
            ])
            ->add('lvcBuyPrice', NumberType::class, [
                'label' => 'Prix Achat LVC (€)',
                'required' => false,
                'scale' => 2,
                'label_attr' => ['class' => $labelClass],
                'attr' => $this->getAttr($controller, ['class' => $inputClass, 'placeholder' => '12.50'], 'buyLvc', true),
                'row_attr' => ['class' => 'mb-2'],
            ])
            ->add('lvcTargetPrice', NumberType::class, [
                'label' => 'Objectif LVC (€)',
                'required' => false,
                'scale' => 2,
                'label_attr' => ['class' => $labelClass],
                'attr' => $this->getAttr($controller, ['class' => $inputClass, 'placeholder' => '15.00'], 'targetLvc'),
                'row_attr' => ['class' => 'mb-2'],
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'Quantité',
                'required' => false,
                'label_attr' => ['class' => 'x-small text-secondary fw-bold d-block mb-1'],
                'attr' => $this->getAttr($controller, [
                    'class' => 'form-control form-control-sm bg-black text-white border-0 fw-bold',
                    'placeholder' => 'Ex: 100'
                ], 'quantity'),
                'row_attr' => ['class' => 'mb-2'],
            ])
            ->add('createdAt', DateType::class, [
                'widget' => 'single_text', // Indispensable pour avoir l'input HTML5 date
                'label' => 'Date de l\'opération',
                'input' => 'datetime_immutable',
                'label_attr' => ['class' => 'x-small text-secondary fw-bold d-block mb-1'],
                'attr' => [
                    'class' => $inputClass,
                    'style' => 'color-scheme: dark;',
                ],
                'row_attr' => ['class' => 'mb-2'],
            ])
            ->add('expiresAt', DateType::class, [
                'widget' => 'single_text', // Pour avoir l'input HTML5 date
                'label' => 'Date de validité',
                'input' => 'datetime_immutable', // Facultatif pour les positions existantes
                'required' => false,
                'label_attr' => ['class' => 'x-small text-secondary fw-bold d-block mb-1'],
                'attr' => [
                    'class' => $inputClass,
                    'style' => 'color-scheme: dark;',
                    'data-position-calculator-target' => 'validityDate' // Cible pour le calcul +3 mois
                ],
                'row_attr' => ['class' => 'mb-2'],
            ]);
    }

    /**
     * Méthode utilitaire pour injecter les attributs Stimulus proprement
     */
    private function getAttr(?string $controller, array $defaultAttr, ?string $target = null, bool $withAction = false): array
    {
        if ($controller && $target) {
            $defaultAttr['data-position-calculator-target'] = $target;
            if ($withAction) {
                $defaultAttr['data-action'] = "input->$controller#calculate";
            }
        }

        return $defaultAttr;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Position::class,
            'csrf_token_id' => 'position_edit',
            // Indispensable pour mapper les erreurs de l'entité vers les champs
            'error_mapping' => [
                'targetPrice' => 'targetPrice',
                'lvcTargetPrice' => 'lvcTargetPrice',
            ],
            'stimulus_controller' => 'position-calculator', // Valeur par défaut
        ]);
        $resolver->setAllowedTypes('stimulus_controller', ['null', 'string']);
    }
}
