<?php
// src/Security/EmailVerifier.php
namespace App\Security;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;
use Symfony\Component\Mailer\MailerInterface;

final class EmailVerifier
{
    public function __construct(
        private VerifyEmailHelperInterface $helper,
        private MailerInterface $mailer
    ) {}

    public function sendEmailConfirmation(User $user, string $verifyRouteName): void
    {
        $signature = $this->helper->generateSignature(
            $verifyRouteName,
            (string)$user->getId(),
            $user->getEmail(),
            ['id' => $user->getId()]
        );

        $email = (new TemplatedEmail())
            ->from(new Address('no-reply@local.test', 'Association D.J.D.T'))
            ->to($user->getEmail())
            ->subject('Vérifiez votre adresse e-mail')
            ->htmlTemplate('security/verify_email.html.twig')
            ->context([
                'signedUrl' => $signature->getSignedUrl(),
                'expiresAt' => $signature->getExpiresAt(),
                'user'      => $user,
            ]);

        $this->mailer->send($email);
    }

    public function handleEmailConfirmation(User $user, string $signedUrl): void
    {
        $this->helper->validateEmailConfirmation($signedUrl, (string)$user->getId(), $user->getEmail());
        $user->setIsVerified(true);
    }
}
