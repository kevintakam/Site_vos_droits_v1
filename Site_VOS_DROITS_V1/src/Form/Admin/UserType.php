<?php
namespace App\Form\Admin;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $requirePassword = (bool) $options['require_password'];

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
            // ---- Champ non mappé pour le mot de passe ----
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Mot de passe',
                'mapped' => false,
                'required' => $requirePassword, // requis seulement en création
                'attr' => ['autocomplete' => 'new-password', 'placeholder' => $requirePassword ? 'Min. 8 caractères' : '(laisser vide pour ne pas changer)'],
                'constraints' => $requirePassword ? [
                    new NotBlank(['message' => 'Le mot de passe est obligatoire.']),
                    new Length(['min' => 8, 'minMessage' => 'Au moins {{ limit }} caractères.']),
                ] : [],
            ])
            // ---- Abonnement / paiements ----
            ->add('subscriptionStatus', ChoiceType::class, [
                'label' => 'Statut abonnement',
                'choices' => [
                    'Actif' => 'active',
                    'Inactif' => 'inactive',
                    'En retard de paiement' => 'past_due',
                    'Annulé' => 'canceled',
                ],
                'required' => false,
                'placeholder' => '—',
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
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            // clé pour savoir si on est en création (true) ou édition (false)
            'require_password' => false,
        ]);
    }
}