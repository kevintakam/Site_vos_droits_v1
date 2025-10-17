<?php

namespace App\Form;

use App\Entity\Contact;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{CheckboxType, EmailType, TelType, TextType, TextareaType};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstname', TextType::class, [
        'label' => 'Prénom', 'required' => true,
        'row_attr' => ['class' => 'field required'],
    ])
    ->add('lastname', TextType::class, [
        'label' => 'Nom', 'required' => true,
        'row_attr' => ['class' => 'field required'],
    ])
    ->add('email', EmailType::class, [
        'label' => 'Email', 'required' => true,
        'row_attr' => ['class' => 'field required'],
    ])
    ->add('phone', TelType::class, [
        'label' => 'Téléphone', 'required' => false,
        'row_attr' => ['class' => 'field'],
    ])
    ->add('message', TextareaType::class, [
        'label' => 'Message', 'required' => true,
        'row_attr' => ['class' => 'field required'],
        'attr' => ['rows' => 6],
    ])
    ->add('is_accepted_cgu', CheckboxType::class, [
        'property_path' => 'isAcceptedCGU',  
        'label' => 'J’accepte les CGU', 'required' => true,
        'row_attr' => ['class' => 'field checkbox required'],
    ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'      => Contact::class,
            'csrf_protection' => true,
        ]);
    }
}
