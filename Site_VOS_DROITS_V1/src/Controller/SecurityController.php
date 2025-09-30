<?php

// src/Controller/SecurityController.php
namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\HttpFoundation\{Request, Response};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Security\EmailVerifier;

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
        // contrôlé par le firewall, ne sera jamais exécuté
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        \App\Repository\UserRepository $userRepo,
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

            // 3) persist
            $em->persist($user);
            $em->flush();

            // 4) ENVOI VÉRIFICATION (passe par Mailpit grâce à MAILER_DSN)
            $emailVerifier->sendEmailConfirmation($user, 'app_verify_email');

            $this->addFlash('success', 'Compte créé ! Vérifiez votre e-mail pour activer votre compte.');
          return $this->redirectToRoute('account_verify_notice');
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
        EmailVerifier $emailVerifier
    ): Response {
        // On récupère l'ID encodé dans l'URL signée (param "id" ajouté lors de la génération)
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
            // Valide cryptographiquement l’URL signée (ève une exception si invalide/expirée)
            $emailVerifier->handleEmailConfirmation($user, $request->getUri());

            // Persistance de l’état vérifié
            $em->flush();

            $this->addFlash('success', 'Votre e-mail a été vérifié. Vous pouvez vous connecter.');
        } catch (\Throwable $e) {
            // Lien invalide, expiré, ou déjà utilisé
            $this->addFlash('error', 'Lien de vérification invalide ou expiré.');
        }

        return $this->redirectToRoute('app_login');
    }

    #[Route('/verify/resend', name: 'app_verify_resend', methods: ['POST'])]
    public function resend(EmailVerifier $emailVerifier): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté.');
            return $this->redirectToRoute('app_login');
        }
        if (method_exists($user, 'isVerified') && $user->isVerified()) {
            $this->addFlash('info', 'Votre compte est déjà vérifié.');
            return $this->redirectToRoute('account');
        }

        $emailVerifier->sendEmailConfirmation($user, 'app_verify_email');
        $this->addFlash('success', 'E-mail de vérification renvoyé.');
        return $this->redirectToRoute('account_verify_notice');
    }
}

