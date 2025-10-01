<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;
use Symfony\Component\Mailer\MailerInterface;
// >>> IMPORTS CORRECTS <<<
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

class EmailVerifier
{
    public function __construct(
        private VerifyEmailHelperInterface $helper,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private string $fromEmail = '',
        private string $fromName = ''
    ) {}

    public function sendEmailConfirmation(User $user, string $routeName): void
    {
        $signature = $this->helper->generateSignature(
            $routeName,
            $user->getId(),
            $user->getEmail(),
            ['id' => $user->getId()]
        );
        $verifyUrl = $signature->getSignedUrl();

        $from = new Address(
            $this->fromEmail ?: (string)($_ENV['APP_FROM_EMAIL'] ?? 'no-reply@example.com'),
            $this->fromName ?: (string)($_ENV['APP_FROM_NAME'] ?? 'Association')
        );

        // >>> TemplatedEmail (et PAS Email) <<<
        $email = (new TemplatedEmail())
            ->from($from)
            ->to(new Address($user->getEmail()))
            ->subject('Vérifie ton adresse email')
            ->htmlTemplate('security/verify_email.html.twig')   // adapte le chemin si besoin
            ->context([
                    'user'      => $user,                          
                    'signedUrl' => $signature->getSignedUrl(),     
                    'expiresAt' => $signature->getExpiresAt(), 
            ]);

        $this->mailer->send($email);
    }

    public function handleEmailConfirmation(User $user, string $fullUri): void
    {
        $this->helper->validateEmailConfirmation($fullUri, $user->getId(), $user->getEmail());
        if (method_exists($user, 'setIsVerified')) {
            $user->setIsVerified(true);
        }
    }
}
