<?php

namespace App\Form;

use App\Entity\Guest;
use App\Enum\Meal;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class GuestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, ['label' => 'Prénom', 'attr' => ['autocomplete' => 'off', 'maxlength' => 80]])
            ->add('lastName', TextType::class, ['label' => 'Nom', 'attr' => ['autocomplete' => 'off', 'maxlength' => 80]])
            ->add('age', IntegerType::class, ['label' => 'Âge', 'attr' => ['min' => 0, 'max' => 110, 'inputmode' => 'numeric', 'data-age' => '']])
            ->add('meal', EnumType::class, [
                'class' => Meal::class,
                'label' => 'Plat',
                'choice_label' => fn (Meal $m) => $m->label(),
                'expanded' => true,
                'placeholder' => false,
            ])
            ->add('comment', TextType::class, [
                'label' => 'Régime ou allergie (facultatif)',
                'required' => false,
                'attr' => ['maxlength' => 255, 'placeholder' => 'Végétarien, sans gluten, allergie aux arachides…', 'autocomplete' => 'off'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Guest::class]);
    }
}
