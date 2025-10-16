<?php
// src/Controller/Admin/UserAdminController.php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\Admin\UserType as AdminUserType;
use App\Form\Admin\UserPasswordType as AdminUserPasswordType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/admin/users', name: 'admin_user_')]
final class UserAdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, UserRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $q      = trim((string) $request->query->get('q', ''));
        $status = trim((string) $request->query->get('status', ''));
        $page   = max(1, (int) $request->query->get('page', 1));
        $limit  = min(100, max(1, (int) $request->query->get('limit', 20)));
        $offset = ($page - 1) * $limit;

        // QueryBuilder basique pour recherche
        $qb = $repo->createQueryBuilder('u')
            ->orderBy('u.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($q !== '') {
            $qb->andWhere('u.email LIKE :q OR u.firstname LIKE :q2 OR u.lastname LIKE :q3')
               ->setParameter('q', '%'.$q.'%')
               ->setParameter('q2', '%'.$q.'%')
               ->setParameter('q3', '%'.$q.'%');
        }

        // Filtre de "statut d’abonnement" si ce champ existe dans votre entité
        if ($status !== '') {
            // Adaptez le champ selon votre modèle (ex.: u.subscriptionStatus)
            $qb->andWhere('u.subscriptionStatus = :s')->setParameter('s', $status);
        }

        $users = $qb->getQuery()->getResult();

        // Total pour pagination simple
        $tqb = $repo->createQueryBuilder('u')->select('COUNT(u.id)');
        if ($q !== '') {
            $tqb->andWhere('u.email LIKE :q OR u.firstname LIKE :q2 OR u.lastname LIKE :q3')
                ->setParameter('q', '%'.$q.'%')
                ->setParameter('q2', '%'.$q.'%')
                ->setParameter('q3', '%'.$q.'%');
        }
        if ($status !== '') {
            $tqb->andWhere('u.subscriptionStatus = :s')->setParameter('s', $status);
        }
        $total = (int) $tqb->getQuery()->getSingleScalarResult();
        $pages = (int) ceil(max(1, $total) / $limit);

        return $this->render('admin/user/index.html.twig', [
            'users' => $users,
            'q'     => $q,
            'status'=> $status,
            'page'  => $page,
            'pages' => $pages,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET','POST'])]
    public function new(Request $request, UserPasswordHasherInterface $hasher): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = new User();
        $form = $this->createForm(AdminUserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Si votre formulaire contient un champ plainPassword
            $plain = $form->has('plainPassword') ? (string) $form->get('plainPassword')->getData() : '';
            if ($plain !== '') {
                $user->setPassword($hasher->hashPassword($user, $plain));
            }

            $this->em->persist($user);
            $this->em->flush();

            $this->addFlash('success', 'Utilisateur créé avec succès.');
            return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
        }

        return $this->render('admin/user/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    public function show(User $user): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // -------- Historique de paiements (optionnel) -----------
        // Si vous avez App\Repository\PaymentRepository, décommentez et injectez-le en argument.
        // $payments = $paymentRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);
        $payments = []; // fallback si pas d’entité Payment/Stripe local

        return $this->render('admin/user/show.html.twig', [
            'user'     => $user,
            'payments' => $payments,
        ]);
    }

    #[Route('/{id<\d+>}/edit', name: 'edit', methods: ['GET','POST'])]
    public function edit(Request $request, User $user): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(AdminUserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->addFlash('success', 'Utilisateur mis à jour.');
            return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
        }

        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id<\d+>}/password', name: 'update_password', methods: ['GET','POST'])]
    public function updatePassword(
        Request $request,
        User $user,
        UserPasswordHasherInterface $hasher
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(AdminUserPasswordType::class, $user, [
            'mapped' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = (string) $form->get('plainPassword')->getData();
            if ($plain !== '') {
                $user->setPassword($hasher->hashPassword($user, $plain));
                $this->em->flush();
                $this->addFlash('success', 'Mot de passe mis à jour.');
                return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
            }
            $this->addFlash('warning', 'Aucun mot de passe fourni.');
        }

        return $this->render('admin/user/password.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, User $user): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete_user_'.$user->getId(), (string) $request->request->get('_token'))) {
            $this->em->remove($user);
            $this->em->flush();
            $this->addFlash('success', 'Utilisateur supprimé.');
        } else {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
        }

        return $this->redirectToRoute('admin_user_index');
    }

    #[Route('/{id<\d+>}/payments/export/{format}', name: 'payments_export', methods: ['GET'], requirements: ['format' => 'xlsx|pdf'])]
    public function paymentsExport(User $user, string $format): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // TODO: remplacez ce stub par votre logique d’export réelle (XLSX/PDF).
        // Nous renvoyons un JSON minimal pour prouver la validité de la route.
        return new JsonResponse([
            'userId' => $user->getId(),
            'format' => $format,
            'status' => 'ok',
            'message'=> 'Point d’export à implémenter (XLSX/PDF).',
        ]);
    }
}
