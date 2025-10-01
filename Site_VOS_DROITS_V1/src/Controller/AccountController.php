<?php


namespace App\Controller;

use App\Form\ProfileType;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Annotation\Route;

#[Route('/mon-compte')]
final class AccountController extends AbstractController
{
    #[Route('', name: 'account', methods: ['GET','POST'])]
    public function index(
        Security $sec,
        SubscriptionRepository $subsRepo,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $sec->getUser();
        if (!$user) {
            return $this->redirectToRoute('auth_login'); // adapte si autre route
        }

        // Profil
        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Profil mis à jour.');
            return $this->redirectToRoute('account');
        }

        // Abonnement (DB)
        $subscription = $subsRepo->findLatestForUser($user);
        $hasActive    = $subscription?->isActive() ?? false;

        // Données Stripe (lecture seule)
        $stripe = [
            'customer'        => null,
            'default_pm'      => null,
            'invoices'        => [],
            'upcomingInvoice' => null,
            'subStripe'       => null,
        ];

        if ($user->getStripeCustomerId()) {
            try {
                \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

                // Client
                $customer = \Stripe\Customer::retrieve($user->getStripeCustomerId());
                $stripe['customer'] = $customer;

                // Payment method par défaut
                $defaultPmId = $customer->invoice_settings?->default_payment_method ?? null;
                if ($defaultPmId) {
                    $stripe['default_pm'] = \Stripe\PaymentMethod::retrieve($defaultPmId);
                } else {
                    $pms = \Stripe\PaymentMethod::all([
                        'customer' => $user->getStripeCustomerId(),
                        'type'     => 'card',
                        'limit'    => 1,
                    ]);
                    $stripe['default_pm'] = $pms->data[0] ?? null;
                }

                // Factures récentes
                $invoices = \Stripe\Invoice::all([
                    'customer' => $user->getStripeCustomerId(),
                    'limit'    => 5,
                ]);
                $stripe['invoices'] = $invoices->data ?? [];

                // Prochaine échéance
                try {
                    $stripe['upcomingInvoice'] = \Stripe\Invoice::upcoming([
                        'customer' => $user->getStripeCustomerId()
                    ]);
                } catch (\Throwable $e) {
                    // pas d’échéance (résilié / fin de période), silencieux
                }

                // Subscription Stripe “complète”
                if ($subscription && $subscription->getStripeSubscriptionId()) {
                    $stripe['subStripe'] = \Stripe\Subscription::retrieve($subscription->getStripeSubscriptionId());
                }
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Impossible de charger certaines données Stripe pour votre compte.');
            }
        }

        return $this->render('account/index.html.twig', [
            'form'          => $form->createView(),
            'subscription'  => $subscription,
            'hasActive'     => $hasActive,
            'stripe'        => $stripe,
        ]);
    }
        #[Route('/verification', name: 'account_verify_notice', methods: ['GET'])]
        public function verifyNotice(): Response
        {
            return $this->render('account/verify_notice.html.twig');
        }
}
