<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Security; 
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;


final class SubscriptionController extends AbstractController
{
  // src/Controller/SubscriptionController.php
#[Route('/abonnement', name: 'subscription')]
public function subscriptionPage(Security $sec, SubscriptionRepository $repo): Response
{
    /** @var User|null $user */
    $user = $sec->getUser();
    $existing = $user ? $repo->findOneBy(['user' => $user], ['id' => 'DESC']) : null;

    return $this->render('subscription/index.html.twig', [
        'hasActiveSubscription' => $existing?->isActive() ?? false,
        'subscription' => $existing,
    ]);
}

#[Route('/abonnement/checkout', name: 'subscription_checkout', methods: ['POST'])]
public function checkout(
    Security $sec,
    Request $request,
    EntityManagerInterface $em
): Response {
    /** @var User|null $user */
    $user = $sec->getUser();
    if (!$user) {
        $this->addFlash('error', 'Veuillez vous connecter pour vous abonner.');
        return $this->redirectToRoute('app_login');
    }
    if (!$user->isVerified()) {
        $this->addFlash('error', 'Veuillez vérifier votre adresse e-mail avant de souscrire.');
        return $this->redirectToRoute('account_verify_notice'); // page d’info vérification
    }

    \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

    // 1) Assurer le Customer Stripe
    if (!$user->getStripeCustomerId()) {
        $customer = \Stripe\Customer::create([
            'email' => $user->getEmail(),
            'name'  => $user->getLastname() ?? $user->getEmail(),
            'metadata' => ['user_id' => (string)$user->getId()],
        ]);
        $user->setStripeCustomerId($customer->id);
        $em->flush();
    }

    // 2) Créer la session de paiement (Checkout)
    $session = \Stripe\Checkout\Session::create([
        'mode' => 'subscription',
        'customer' => $user->getStripeCustomerId(),
        'line_items' => [[
            'price'    => $_ENV['STRIPE_PRICE_BASIC'],
            'quantity' => 1,
        ]],
        'allow_promotion_codes' => false,
        'success_url' => rtrim($_ENV['APP_URL'], '/').$this->generateUrl('subscription_success').'?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'  => rtrim($_ENV['APP_URL'], '/').$this->generateUrl('subscription'),
        'automatic_tax' => ['enabled' => false],
        'locale' => 'fr',
    ]);

    return $this->redirect($session->url);
}

#[Route('/abonnement/success', name: 'subscription_success', methods: ['GET'])]
public function success(Request $request): Response
{
    // Affiche simple message → l’activation réelle se fait via Webhook
    return $this->render('subscription/success.html.twig');
}
#[Route('/abonnement/portal', name: 'subscription_portal', methods: ['POST'])]
public function portal(Security $sec): Response
{
    /** @var \App\Entity\User|null $user */
    $user = $sec->getUser();
    if (!$user || !$user->getStripeCustomerId()) {
        $this->addFlash('error', 'Aucun compte Stripe trouvé.');
        return $this->redirectToRoute('subscription');
    }

    \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

    // Paramètres de base requis
    $params = [
        'customer'   => $user->getStripeCustomerId(),
        'return_url' => rtrim($_ENV['APP_URL'], '/').$this->generateUrl('account'),
    ];

    // 👇 Si une configuration spécifique est définie, on l’utilise
    if (!empty($_ENV['STRIPE_PORTAL_CONFIGURATION_ID'])) {
        $params['configuration'] = $_ENV['STRIPE_PORTAL_CONFIGURATION_ID'];
    }

    try {
        $session = \Stripe\BillingPortal\Session::create($params);
        return $this->redirect($session->url);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        // Erreurs Stripe (ex: portail non configuré en mode test)
        $this->addFlash('error', 'Impossible d’ouvrir le portail client Stripe : '.$e->getMessage());
    } catch (\Throwable $e) {
        // Autres erreurs (réseau, etc.)
        $this->addFlash('error', 'Une erreur est survenue lors de l’ouverture du portail client.');
    }

    return $this->redirectToRoute('account');
}


}
