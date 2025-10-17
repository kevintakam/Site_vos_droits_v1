<?php
declare(strict_types=1);
// src/Repository/SubscriptionRepository.php

namespace App\Repository;

use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository des abonnements utilisateurs (Stripe).
 *
 * Fournit :
 *  - Lecture ordonnée des abonnements d’un utilisateur,
 *  - Abonnement courant / dernier,
 *  - Indicateur d’activité fonctionnelle (isActive),
 *  - Normalisation des abonnements pour les vues (Twig) et l’export.
 */
final class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    /**
     * Abonnements d’un user, du plus récent au plus ancien (période fin puis id).
     *
     * @return Subscription[]
     */
    public function findByUserOrdered(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.user = :u')->setParameter('u', $user)
            ->orderBy('s.currentPeriodEnd', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Abonnement “courant” = celui avec la periodEnd la plus récente (ou dernier créé).
     */
    public function findCurrentForUser(User $user): ?Subscription
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.user = :u')->setParameter('u', $user)
            ->orderBy('s.currentPeriodEnd', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Dernier abonnement (fallback par id si pas de periodEnd).
     */
    public function findLatestForUser(User $user): ?Subscription
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.user = :u')->setParameter('u', $user)
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Vrai si un abonnement “actif” existe à l’instant T (via Subscription::isActive()).
     */
    public function hasActiveForUser(User $user): bool
    {
        $sub = $this->findCurrentForUser($user) ?? $this->findLatestForUser($user);
        return $sub?->isActive() ?? false;
    }

    // ---------------------------------------------------------------------
    // Normalisation pour Vue (Twig) et Export
    // ---------------------------------------------------------------------

    /**
     * Normalise un objet Subscription pour l’affichage dans Twig.
     * Retourne uniquement des scalaires / DateTime pour éviter les surprises.
     *
     * @return array{
     *   id:int,
     *   status:string,
     *   statusLabel:string,
     *   isActive:bool,
     *   periodEnd:?\DateTimeImmutable,
     *   stripeSubId:string
     * }
     */
    public function normalizeForView(Subscription $s): array
    {
        return [
            'id'          => (int) $s->getId(),
            'status'      => $s->getStatus(),
            'statusLabel' => $s->getStatusLabel(),
            'isActive'    => $s->isActive(),
            'periodEnd'   => $s->getCurrentPeriodEnd(),
            'stripeSubId' => $s->getStripeSubscriptionId(),
        ];
    }

    /**
     * Normalise la liste ordonnée des abonnements d’un user (pour Twig).
     *
     * @return array<int, array{
     *   id:int,
     *   status:string,
     *   statusLabel:string,
     *   isActive:bool,
     *   periodEnd:?\DateTimeImmutable,
     *   stripeSubId:string
     * }>
     */
    public function normalizedListForUser(User $user): array
    {
        return array_map(
            fn (Subscription $s) => $this->normalizeForView($s),
            $this->findByUserOrdered($user)
        );
    }

    /**
     * Construit des lignes prêtes à l’export (XLSX/PDF) conformément
     * au format utilisé dans le contrôleur d’export.
     *
     * @return array<int, array{
     *   id:int|string,
     *   date:string,
     *   amount:string,
     *   currency:string,
     *   status:string,
     *   reference:string,
     *   invoice:string
     * }>
     */
    public function exportRowsForUser(User $user): array
    {
        $now = new \DateTimeImmutable('now');

        return array_map(function (Subscription $s) use ($now) {
            $periodEnd = $s->getCurrentPeriodEnd();
            $activeLike = \in_array($s->getStatus(), [Subscription::STATUS_ACTIVE, Subscription::STATUS_PAST_DUE], true)
                          && $periodEnd && $periodEnd > $now;

            return [
                'id'        => (int) $s->getId(),
                'date'      => $periodEnd ? $periodEnd->format('d/m/Y H:i') : '',
                'amount'    => '', // pas de montant au niveau Subscription
                'currency'  => '',
                'status'    => $s->getStatus() . ($activeLike ? ' (actif)' : ''),
                'reference' => $s->getStripeSubscriptionId(),
                'invoice'   => '', // pas de facture URL ici (Stripe invoice = autre endpoint)
            ];
        }, $this->findByUserOrdered($user));
    }
}