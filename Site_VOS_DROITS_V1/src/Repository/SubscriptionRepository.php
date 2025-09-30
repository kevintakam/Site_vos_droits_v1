<?php
// src/Repository/SubscriptionRepository.php
namespace App\Repository;

use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    /** Dernière subscription connue d’un utilisateur */
    public function findLatestForUser(User $user): ?Subscription
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.user = :u')->setParameter('u', $user)
            ->orderBy('s.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    /** Vrai si une subscription active existe à l’instant T */
    public function hasActiveForUser(User $user): bool
    {
        $sub = $this->findLatestForUser($user);
        return $sub?->isActive() ?? false;
    }
}
