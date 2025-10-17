<?php

// src/Controller/SecurityController.php
namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\HttpFoundation\{Request, Response};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Security\EmailVerifier;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use Psr\Log\LoggerInterface;


final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        $customError = null;
        if ($error) {
            $customError = 'Identifiants incorrects. Veuillez réessayer.';
        }

        return $this->render('security/index.html.twig', [
            'last_username' => $lastUsername,
            'error' => $customError,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        UserRepository $userRepo,
        EmailVerifier $emailVerifier
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // 1) email existant ?
            if ($userRepo->findOneBy(['email' => $user->getEmail()])) {
                $this->addFlash('error', 'Un compte avec cet email existe déjà. Veuillez vous connecter.');
                return $this->redirectToRoute('app_login');
            }
            // 2) hash du mot de passe
            $user->setPassword(
                $hasher->hashPassword($user, $form->get('plainPassword')->getData())
            );
            $em->persist($user);
            $em->flush();

            // 3) envoi vérif
            $emailVerifier->sendEmailConfirmation($user, 'app_verify_email');

            $this->addFlash('success', 'Compte créé ! Vérifiez votre e-mail pour activer votre compte.');
            return $this->redirectToRoute('account_verify_notice', ['email' => $user->getEmail()]);
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
#[Route('/verify/email', name: 'app_verify_email', methods: ['GET'])]
public function verifyEmail(
    Request $request,
    UserRepository $users,
    EntityManagerInterface $em,
    EmailVerifier $emailVerifier,
    LoggerInterface $logger // <= injection si possible
): Response {
    $id = $request->query->get('id');
    if (!$id) {
        $this->addFlash('error', 'Lien invalide.');
        return $this->redirectToRoute('app_login');
    }
    $user = $users->find($id);
    if (!$user) {
        $this->addFlash('error', 'Utilisateur introuvable.');
        return $this->redirectToRoute('app_login');
    }

    try {
        $emailVerifier->handleEmailConfirmation($user, $request->getUri());
        $em->flush();
        $this->addFlash('success', 'Votre e-mail a été vérifié. Vous pouvez vous connecter.');
        return $this->redirectToRoute('app_login');
    } catch (VerifyEmailExceptionInterface $e) {
        // ->getReason() vous dira exactement : expired / invalid_signature / already_verified...
        $logger->warning('VerifyEmail failed', [
            'reason' => $e->getReason(),
            'class'  => get_class($e),
            'uri'    => $request->getUri(),
            'userId' => $user->getId(),
            'email'  => $user->getEmail(),
        ]);
        $this->addFlash('error', sprintf('Vérification impossible : %s', $e->getReason()));
        return $this->redirectToRoute('account_verify_notice', ['email' => $user->getEmail()]);
    }
}

    #[Route('/verify/resend', name: 'app_verify_resend', methods: ['POST'])]
    public function resendPublic(
        Request $request,
        UserRepository $users,
        EmailVerifier $emailVerifier
    ): Response {
        $email = trim((string) $request->request->get('email'));
        if (!$email) {
            $this->addFlash('error', 'Veuillez renseigner votre e-mail.');
            return $this->redirectToRoute('account_verify_notice');
        }

        $user = $users->findOneBy(['email' => $email]);
        // Ne pas révéler si un e-mail existe ou non
        if ($user && method_exists($user, 'isVerified') && !$user->isVerified()) {
            // TODO: anti-abus (throttle/captcha) si besoin
            $emailVerifier->sendEmailConfirmation($user, 'app_verify_email');
        }

        $this->addFlash('success', 'Si un compte existe pour cet e-mail, un nouveau lien vient d’être envoyé.');
        return $this->redirectToRoute('account_verify_notice', ['email' => $email]);
    }

    #[Route('/verification', name: 'account_verify_notice', methods: ['GET'])]
    public function verifyNotice(Request $request): Response
    {
        return $this->render('account/verify_notice.html.twig', [
            'prefill_email' => (string) $request->query->get('email', ''),
        ]);
    }
}
