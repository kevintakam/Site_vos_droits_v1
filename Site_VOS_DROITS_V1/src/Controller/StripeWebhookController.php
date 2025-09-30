<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;       
use App\Repository\UserRepository;      
use App\Repository\SubscriptionRepository;   
use Stripe\Webhook;     

final class StripeWebhookController extends AbstractController
{
// src/Controller/StripeWebhookController.php
#[Route('/stripe/webhook', name: 'stripe_webhook', methods: ['POST'])]
public function webhook(Request $request, EntityManagerInterface $em, UserRepository $users, SubscriptionRepository $subs): Response
{
    $payload = $request->getContent();
    $sigHeader = $request->headers->get('stripe-signature');
    $secret = $_ENV['STRIPE_WEBHOOK_SECRET'];

    try {
        $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
    } catch (\Throwable $e) {
        return new Response('Invalid signature', 400);
    }
  \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
    switch ($event->type) {

        case 'checkout.session.completed':
            /** @var \Stripe\Checkout\Session $session */
            $session = $event->data->object;
            if ($session->mode === 'subscription' && $session->customer && $session->subscription) {
                $stripeCustomerId = (string)$session->customer;
                $stripeSubscriptionId = (string)$session->subscription;

                $user = $users->findOneBy(['stripeCustomerId' => $stripeCustomerId]);
                if ($user) {
                    // Récupérer la sub complète (dates)
                    \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
                    $sub = \Stripe\Subscription::retrieve($stripeSubscriptionId);

                    $entity = $subs->findOneBy(['stripeSubscriptionId' => $stripeSubscriptionId]) ?? new \App\Entity\Subscription();
                    $entity->setUser($user);
                    $entity->setStripeSubscriptionId($stripeSubscriptionId);
                    $entity->setStatus($sub->status);
                    $entity->setCurrentPeriodEnd(new \DateTimeImmutable('@'.$sub->current_period_end));
                    $em->persist($entity);
                    $em->flush();
                }
            }
            break;

        case 'customer.subscription.updated':
        case 'customer.subscription.deleted':
            /** @var \Stripe\Subscription $sub */
            $sub = $event->data->object;
            $entity = $subs->findOneBy(['stripeSubscriptionId' => $sub->id]);
            if ($entity) {
                $entity->setStatus($sub->status);
                $entity->setCurrentPeriodEnd(
                    $sub->current_period_end ? new \DateTimeImmutable('@'.$sub->current_period_end) : null
                );
                $em->flush();
            }
            break;

        case 'invoice.paid':
        case 'invoice.payment_failed':
            // Optionnel : journaliser / notifier / marquer past_due
            break;
    }

    return new Response('ok', 200);
}

}
