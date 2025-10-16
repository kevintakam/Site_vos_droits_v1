<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Recherche paginée avec filtres texte, statut de compte et statut d'abonnement.
     *
     * $status accepte :
     *   - Compte     : 'verified' | 'unverified' | 'admin' | 'user'
     *   - Abonnement : 'active' | 'past_due' | 'canceled' | 'inactive'
     *
     * @param string|null $q      Terme de recherche (prénom/nom/email/téléphone)
     * @param string|null $status Statut unique (compte OU abonnement)
     * @param int         $page   Page 1..N
     * @param int         $limit  Résultats par page (1..200)
     * @return array{results: User[], total: int}
     */
    public function search(?string $q, ?string $status, int $page = 1, int $limit = 20): array
    {
        $page   = max(1, $page);
        $limit  = max(1, min(200, $limit));
        $offset = ($page - 1) * $limit;

        $qb = $this->createQueryBuilder('u');

        // --- Filtre texte ----------------------------------------------------
        if ($q !== null && $q !== '') {
            $qb->andWhere('u.firstname LIKE :q OR u.lastname LIKE :q OR u.email LIKE :q OR u.phone LIKE :q')
               ->setParameter('q', '%'.$q.'%');
        }

        // --- Filtres "compte" -----------------------------------------------
        if ($status === 'verified') {
            $qb->andWhere('u.isVerified = :v')->setParameter('v', true);
        } elseif ($status === 'unverified') {
            $qb->andWhere('u.isVerified = :v')->setParameter('v', false);
        } elseif ($status === 'admin') {
            // JSON rôles stocké en texte -> LIKE compatible SQLite/MySQL
            $qb->andWhere('u.roles LIKE :r')->setParameter('r', '%ROLE_ADMIN%');
        } elseif ($status === 'user') {
            $qb->andWhere('u.roles NOT LIKE :r')->setParameter('r', '%ROLE_ADMIN%');
        }

        // --- Filtres "abonnement" (Stripe-like) ------------------------------
        // Propriété d'entité : subscriptionStatus (colonne subscription_status)
        if (in_array($status, ['active', 'past_due', 'canceled'], true)) {
            $qb->andWhere('u.subscriptionStatus = :subs')->setParameter('subs', $status);
        } elseif ($status === 'inactive') {
            // Interprétation par défaut : aucun statut stocké (NULL ou '')
            $qb->andWhere('(u.subscriptionStatus IS NULL OR u.subscriptionStatus = \'\')');
            // Si vous stockez explicitement "inactive", utilisez plutôt :
            // $qb->andWhere('u.subscriptionStatus = :subs')->setParameter('subs', 'inactive');
        }

        // --- Tri / pagination ------------------------------------------------
        $qb->orderBy('u.id', 'ASC');

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();

        $results = $qb->setFirstResult($offset)
                      ->setMaxResults($limit)
                      ->getQuery()
                      ->getResult();

        return ['results' => $results, 'total' => $total];
    }

    /**
     * Rehash automatique du mot de passe au fil du temps.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }
}