<?php

namespace App\Form;

use App\Entity\Booking;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;

final class BookingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, ['label' => 'Prénom', 'attr' => ['autocomplete' => 'given-name', 'maxlength' => 80]])
            ->add('lastName', TextType::class, ['label' => 'Nom', 'attr' => ['autocomplete' => 'family-name', 'maxlength' => 80]])
            ->add('email', EmailType::class, ['label' => 'E-mail', 'help' => 'Vous y recevrez votre billet.', 'attr' => ['autocomplete' => 'email']])
            ->add('phone', TelType::class, ['label' => 'Téléphone', 'attr' => ['autocomplete' => 'tel']])
            ->add('guests', CollectionType::class, [
                'entry_type' => GuestType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => false,
                'prototype_name' => '__index__',
            ])
            ->add('consent', CheckboxType::class, [
                'mapped' => false,
                'label' => 'J\'accepte que ces informations soient utilisées pour organiser la soirée.',
                'constraints' => [new IsTrue(message: 'Merci de cocher cette case pour continuer.')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Booking::class]);
    }
}
