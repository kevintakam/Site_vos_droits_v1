<?php

namespace App\Entity;

use App\Repository\ContactRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContactRepository::class)]
class Contact
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
     #[Assert\NotBlank(message: "Le prénom est obligatoire.")]
    #[Assert\Length(max: 255)]
    private ?string $firstname = null;

    #[ORM\Column(length: 255)]
     #[Assert\NotBlank(message: "Le nom est obligatoire.")]
    #[Assert\Length(max: 255)]
    private ?string $lastname = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "L'email est obligatoire.")]
    #[Assert\Email(message: "Veuillez saisir un email valide.")]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
     private ?string $phone = null;

    #[ORM\Column(type: Types::TEXT)]
     #[Assert\NotBlank(message: "Le message est obligatoire.")]
    #[Assert\Length(min: 10, minMessage: "Le message doit contenir au moins {{ limit }} caractères.")]
    private ?string $message = null;

    #[ORM\Column]
    #[Assert\IsTrue(message: "Vous devez accepter les CGU pour continuer.")]
    private ?bool $isAcceptedCGU = null;



    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

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

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function isAcceptedCGU(): ?bool
    {
        return $this->isAcceptedCGU;
    }

    public function setIsAcceptedCGU(bool $isAcceptedCGU): static
    {
        $this->isAcceptedCGU = $isAcceptedCGU;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
