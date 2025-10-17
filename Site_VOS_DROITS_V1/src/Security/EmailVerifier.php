<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final class EmailVerifier
{
    public function __construct(
        private readonly VerifyEmailHelperInterface $helper,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        // Injectés depuis services.yaml (bind)
        private readonly string $fromEmail,
        private readonly string $fromName,
    ) {}

    /**
     * Envoie l’e-mail de vérification (lien signé).
     * Le template Twig peut utiliser: user, signedUrl, expiresAt.
     */
    public function sendEmailConfirmation(User $user, string $routeName): void
    {
        // L’utilisateur doit être persisté/flush pour avoir un ID non null
        $userId = $user->getId();
        if ($userId === null) {
            throw new \LogicException(
                'L’utilisateur doit être persisté et flush avant l’envoi de l’email de vérification.'
            );
        }

        $signature = $this->helper->generateSignature(
            $routeName,
            (string) $userId,             // 2e argument attendu: string
            (string) $user->getEmail(),
            ['id' => (string) $userId]    // param route si nécessaire
        );

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to(new Address($user->getEmail()))
            ->subject('Vérifie ton adresse email')
            ->htmlTemplate('security/verify_email.html.twig')   // adaptez le chemin si besoin
            ->context([
                'user'      => $user,
                'signedUrl' => $signature->getSignedUrl(),
                'expiresAt' => $signature->getExpiresAt(),
            ]);

        $this->mailer->send($email);
    }

    /**
     * Valide le lien de vérification et marque l’utilisateur comme vérifié si le setter existe.
     */
    public function handleEmailConfirmation(User $user, string $fullUri): void
    {
        $this->helper->validateEmailConfirmation($fullUri, $user->getId(), $user->getEmail());

        if (method_exists($user, 'setIsVerified')) {
            $user->setIsVerified(true);
        }
    }
}
