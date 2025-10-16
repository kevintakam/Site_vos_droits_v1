<?php
namespace App\Form\Admin;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
            ])
            ->add('firstname', TextType::class, [
                'label' => 'Prénom',
            ])
            ->add('lastname', TextType::class, [
                'label' => 'Nom',
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
            ])
            ->add('subscriptionStatus', ChoiceType::class, [
                'label' => 'Statut abonnement',
                'choices' => [
                    'Actif' => 'active',
                    'Inactif' => 'inactive',
                    'En retard de paiement' => 'past_due',
                    'Annulé' => 'canceled',
                ],
            ])
            ->add('lastPaymentAt', DateTimeType::class, [
                'label' => 'Dernier paiement',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('stripeCustomerId', TextType::class, [
                'label' => 'Stripe Customer ID',
                'required' => false,
            ])
            ->add('stripeSubscriptionId', TextType::class, [
                'label' => 'Stripe Subscription ID',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}