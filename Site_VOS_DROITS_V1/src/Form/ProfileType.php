<?php

// src/Form/ProfileType.php
namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{EmailType, TelType, TextType};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\User;

final class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $options): void
    {
        $b->add('firstname', TextType::class, ['required' => false, 'label' => 'Prénom'])
          ->add('lastname',  TextType::class, ['required' => false, 'label' => 'Nom'])
          ->add('email',     EmailType::class, ['required' => true,  'label' => 'Email'])
          ->add('phone',     TelType::class, ['required' => false, 'label' => 'Téléphone']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}

