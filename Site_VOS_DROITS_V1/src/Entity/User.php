<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    private ?string $firstname = null;

    #[ORM\Column(length: 255)]
    private ?string $lastname = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column]
    private ?bool $isVerified = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeCustomerId = null;

    /* === AJOUTS Nécessaires pour l’admin abonnement === */

    // Statut d’abonnement: 'active' | 'inactive' | 'past_due' | 'canceled'
    #[ORM\Column(length: 20, options: ['default' => 'inactive'])]
    private string $subscriptionStatus = 'inactive';

    // Dernière date de paiement confirmé (UTC)
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastPaymentAt = null;

    // Identifiant d’abonnement Stripe (si présent)
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $stripeSubscriptionId = null;

    /* ================================================== */

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): static
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function isVerified(): ?bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getStripeCustomerId(): ?string { return $this->stripeCustomerId; }
    public function setStripeCustomerId(?string $v): self { $this->stripeCustomerId = $v; return $this; }

    /* === Getters/Setters ajoutés === */

    public function getSubscriptionStatus(): string
    {
        return $this->subscriptionStatus;
    }

    public function setSubscriptionStatus(string $status): self
    {
        $this->subscriptionStatus = $status;
        return $this;
    }

    public function getLastPaymentAt(): ?\DateTimeInterface
    {
        return $this->lastPaymentAt;
    }

    public function setLastPaymentAt(?\DateTimeInterface $dt): self
    {
        $this->lastPaymentAt = $dt;
        return $this;
    }

    public function getStripeSubscriptionId(): ?string
    {
        return $this->stripeSubscriptionId;
    }

    public function setStripeSubscriptionId(?string $id): self
    {
        $this->stripeSubscriptionId = $id;
        return $this;
    }
}