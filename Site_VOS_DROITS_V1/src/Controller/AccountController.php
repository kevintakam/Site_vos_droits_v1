<?php

namespace App\Controller;

use App\Form\ProfileType;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Annotation\Route;

#[Route('/mon-compte')]
final class AccountController extends AbstractController
{
    #[Route('', name: 'account', methods: ['GET','POST'])]
    public function index(Security $sec, SubscriptionRepository $subsRepo, Request $request, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user = $sec->getUser();

        $form = $this->createForm(ProfileType::class, $user, [
            'method' => 'POST',
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Profil mis à jour.');
            return $this->redirectToRoute('account');
        }

        $sub = $subsRepo->findOneBy(['user' => $user], ['id' => 'DESC']);

        return $this->render('account/index.html.twig', [
            'form' => $form->createView(),
            'subscription' => $sub,
        ]);
    }
}
