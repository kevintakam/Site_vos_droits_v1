<?php
// src/Controller/StripeWebhookController.php
namespace App\Controller;

use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Annotation\Route;

final class StripeWebhookController extends AbstractController
{
    #[Route('/stripe/webhook', name: 'stripe_webhook', methods: ['POST'])]
    public function webhook(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $users,
        SubscriptionRepository $subs,
        LoggerInterface $logger
    ): Response {
        $payload   = $request->getContent();
        $sigHeader = $request->headers->get('stripe-signature');
        $secret    = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '';

        // 0) TRACE d’entrée
        $logger->info('[stripe] webhook hit', [
            'length' => strlen($payload),
            'sig'    => $sigHeader ? 'present' : 'missing',
        ]);

        try {
            // 1) Vérif signature
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
            $logger->info('[stripe] event parsed', ['type' => $event->type]);

            // 2) Clé API
            \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY'] ?? '');
            if (empty($_ENV['STRIPE_SECRET_KEY'])) {
                $logger->warning('[stripe] STRIPE_SECRET_KEY missing');
            }

            // 3) Handling
            switch ($event->type) {
                case 'checkout.session.completed': {
                    /** @var \Stripe\Checkout\Session $session */
                    $session = $event->data->object;

                    $logger->info('[stripe] checkout.session.completed payload', [
                        'mode'         => $session->mode ?? null,
                        'customer'     => $session->customer ?? null,
                        'subscription' => $session->subscription ?? null,
                        'payment'      => $session->payment_status ?? null,
                    ]);

                    if (($session->mode ?? null) === 'subscription'
                        && ($session->customer ?? null)
                        && ($session->subscription ?? null)
                    ) {
                        $stripeCustomerId     = (string) $session->customer;
                        $stripeSubscriptionId = (string) $session->subscription;

                        $user = $users->findOneBy(['stripeCustomerId' => $stripeCustomerId]);
                        if (!$user) {
                            $logger->warning('[stripe] user not found by stripeCustomerId', [
                                'stripeCustomerId' => $stripeCustomerId,
                            ]);
                            break; // on sort proprement (200)
                        }

                        // Récupération robuste de la sub
                        try {
                            $sub = \Stripe\Subscription::retrieve($stripeSubscriptionId);
                        } catch (\Throwable $e) {
                            $logger->error('[stripe] Subscription::retrieve failed', [
                                'subId' => $stripeSubscriptionId,
                                'err'   => $e->getMessage(),
                            ]);
                            break;
                        }

                        // Upsert
                        $entity = $subs->findOneBy(['stripeSubscriptionId' => $stripeSubscriptionId])
                                ?? new \App\Entity\Subscription();

                        $entity->setUser($user);
                        $entity->setStripeSubscriptionId($stripeSubscriptionId);
                        $entity->setStatus($sub->status ?? 'unknown');
                        $entity->setCurrentPeriodEnd(
                                !empty($sub->current_period_end)
                                    ? new \DateTimeImmutable('@'.$sub->current_period_end)
                                    : (new \DateTimeImmutable('now'))->modify('+1 month')
                            );
                        try {
                            $em->persist($entity);
                            $em->flush();
                            $logger->info('[stripe] subscription upserted', [
                                'userId'  => $user->getId(),
                                'subId'   => $stripeSubscriptionId,
                                'status'  => $sub->status ?? null,
                                'period'  => $sub->current_period_end ?? null,
                            ]);
                        } catch (\Throwable $e) {
                            $logger->error('[stripe] Doctrine flush failed', [
                                'err' => $e->getMessage(),
                            ]);
                            // on renvoie 200 pour éviter les retries infinis,
                            // mais on garde l’erreur en log pour corriger.
                        }
                    }
                    break;
                }

                case 'customer.subscription.updated':
                case 'customer.subscription.deleted': {
                    /** @var \Stripe\Subscription $sub */
                    $sub = $event->data->object;
                    $logger->info('[stripe] subscription lifecycle', [
                        'type'   => $event->type,
                        'subId'  => $sub->id ?? null,
                        'status' => $sub->status ?? null,
                        'end'    => $sub->current_period_end ?? null,
                    ]);

                    if (!empty($sub->id)) {
                        $entity = $subs->findOneBy(['stripeSubscriptionId' => $sub->id]);
                        if ($entity) {
                            $entity->setStatus($sub->status ?? 'unknown');
                            $entity->setCurrentPeriodEnd(
                                !empty($sub->current_period_end)
                                    ? new \DateTimeImmutable('@'.$sub->current_period_end)
                                    : (new \DateTimeImmutable('now'))->modify('+1 month')
                            );

                            try {
                                $em->flush();
                                $logger->info('[stripe] subscription updated in DB');
                            } catch (\Throwable $e) {
                                $logger->error('[stripe] Doctrine flush failed (update)', [
                                    'err' => $e->getMessage(),
                                ]);
                            }
                        } else {
                            $logger->warning('[stripe] subscription entity not found for update', [
                                'subId' => $sub->id,
                            ]);
                        }
                    }
                    break;
                }

                default:
                    $logger->info('[stripe] event ignored', ['type' => $event->type]);
            }
        } catch (\Throwable $e) {
            // 🔥 N’importe quelle exception → on log, et on renvoie 200 pour stopper les retries Stripe
            $logger->error('[stripe] unhandled exception', [
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return new Response('ok', 200);
        }

        return new Response('ok', 200);
    }
}
