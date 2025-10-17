<?php
namespace App\Form\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class UserPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('plainPassword', PasswordType::class, [
            'label' => 'Nouveau mot de passe',
            'mapped' => false,
            'attr' => ['autocomplete' => 'new-password'],
            'constraints' => [
                new Assert\NotBlank(),
                new Assert\Length(min: 8),
            ],
        ]);
    }
}