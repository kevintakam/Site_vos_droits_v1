<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\Table(name: 'subscription')]
#[ORM\Index(columns: ['status'], name: 'idx_subscription_status')]
#[ORM\Index(columns: ['current_period_end'], name: 'idx_subscription_period_end')]
#[ORM\Index(columns: ['user_id'], name: 'idx_subscription_user')]
#[ORM\HasLifecycleCallbacks]
class Subscription
{
    public const STATUS_ACTIVE     = 'active';
    public const STATUS_CANCELED   = 'canceled';
    public const STATUS_INCOMPLETE = 'incomplete';
    public const STATUS_PAST_DUE   = 'past_due';
    public const STATUS_UNPAID     = 'unpaid';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    // Si votre entité User déclare un "subscriptions" côté inverse, ajoutez "inversedBy: 'subscriptions'"
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $stripeSubscriptionId;

    // Valeurs attendues: active|canceled|incomplete|past_due|unpaid
    #[ORM\Column(length: 32)]
    private string $status = self::STATUS_UNPAID;

    // Fin de la période en cours (Stripe current_period_end)
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $currentPeriodEnd = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable('now');
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->status    = self::STATUS_UNPAID;
        $this->stripeSubscriptionId = ''; // à setter explicitement
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable('now');
        $this->createdAt = $this->createdAt ?? $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now');
    }

    // ------------------
    // Getters / Setters
    // ------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getStripeSubscriptionId(): string
    {
        return $this->stripeSubscriptionId;
    }

    public function setStripeSubscriptionId(string $stripeSubscriptionId): self
    {
        $this->stripeSubscriptionId = $stripeSubscriptionId;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getCurrentPeriodEnd(): ?\DateTimeImmutable
    {
        return $this->currentPeriodEnd;
    }

    public function setCurrentPeriodEnd(?\DateTimeImmutable $currentPeriodEnd): self
    {
        $this->currentPeriodEnd = $currentPeriodEnd;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    // ---------------
    // Helper methods
    // ---------------

    /**
     * Abonnement considéré "actif" fonctionnellement :
     * - status dans [active, past_due]
     * - ET currentPeriodEnd futur.
     */
    public function isActive(): bool
    {
        if (!$this->currentPeriodEnd) {
            return false;
        }
        $now = new \DateTimeImmutable('now');

        return \in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_PAST_DUE], true)
            && $this->currentPeriodEnd > $now;
    }

    public function isPastDue(): bool
    {
        return $this->status === self::STATUS_PAST_DUE;
    }

    public function isCanceled(): bool
    {
        return $this->status === self::STATUS_CANCELED;
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE     => 'Actif',
            self::STATUS_PAST_DUE   => 'En retard',
            self::STATUS_CANCELED   => 'Annulé',
            self::STATUS_INCOMPLETE => 'Incomplet',
            self::STATUS_UNPAID     => 'Impayé',
            default                 => 'Inconnu',
        };
    }

    public function __toString(): string
    {
        return sprintf(
            'Subscription#%s [%s] %s',
            $this->id ?? 'new',
            $this->status,
            $this->stripeSubscriptionId
        );
    }
}