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

        // Recherche + pagination
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

        if ($status !== '') {
            $qb->andWhere('u.subscriptionStatus = :s')->setParameter('s', $status);
        }

        $users = $qb->getQuery()->getResult();

        // Total pour pagination
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

        $payments = []; // fallback (Stripe gère les paiements)

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

    // ==========================
    // EXPORT UTILISATEUR (CSV)
    // ==========================
    #[Route('/{id<\d+>}/payments/export/{format}', name: 'payments_export', methods: ['GET'], requirements: ['format' => 'csv|pdf'])]
    public function paymentsExport(User $user, string $format = 'csv'): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // ---- Dernier paiement Stripe (si dispo) ----
        $lastDate = null;
        $lastAmountCents = null;
        $lastCurrency = 'EUR';
        $lastStatus = null;
        $lastRef = null;
        $lastInvoiceUrl = null;

        $secret = $_ENV['STRIPE_SECRET_KEY'] ?? $_SERVER['STRIPE_SECRET_KEY'] ?? null;
        $customerId = method_exists($user, 'getStripeCustomerId') ? $user->getStripeCustomerId() : null;

        if ($secret && $customerId) {
            try {
                $stripe = new \Stripe\StripeClient($secret);
                $invoices = $stripe->invoices->all([
                    'customer' => $customerId,
                    'limit'    => 1,
                    'expand'   => ['data.charge'],
                ]);
                if (!empty($invoices->data)) {
                    $inv = $invoices->data[0];
                    $lastDate        = isset($inv->status_transitions->paid_at) && $inv->status_transitions->paid_at
                        ? (new \DateTime('@'.$inv->status_transitions->paid_at))
                        : (isset($inv->created) ? new \DateTime('@'.$inv->created) : null);
                    $lastAmountCents = $inv->amount_paid ?? $inv->amount_due ?? null;
                    $lastCurrency    = strtoupper($inv->currency ?? 'EUR');
                    $lastStatus      = $inv->status ?? null;
                    $lastRef         = $inv->number ?? $inv->id ?? null;
                    $lastInvoiceUrl  = $inv->hosted_invoice_url ?? null;
                }
            } catch (\Throwable $e) {
                // on ne bloque pas l'export
            }
        }

        // ---- Données utilisateur ----
        $roles = method_exists($user, 'getRoles') ? implode(', ', (array) $user->getRoles()) : '';
        $createdAt = (method_exists($user, 'getCreatedAt') && $user->getCreatedAt() instanceof \DateTimeInterface)
            ? $user->getCreatedAt()->format('d/m/Y H:i')
            : '—';

        $rows = [
            ['Champ', 'Valeur'],
            ['Prénom', (string) ($user->getFirstname() ?? '')],
            ['Nom', (string) ($user->getLastname() ?? '')],
            ['Email', (string) $user->getEmail()],
            ['Téléphone', method_exists($user,'getPhone') ? (string) ($user->getPhone() ?? '') : ''],
            ['Vérifié', (method_exists($user,'isVerified') && $user->isVerified()) ? 'Oui' : 'Non'],
            ['Rôles', $roles],
            ['Créé le', $createdAt],
            ['Statut abonnement', method_exists($user,'getSubscriptionStatus') ? (string) ($user->getSubscriptionStatus() ?? '') : ''],
            ['Stripe Customer', method_exists($user,'getStripeCustomerId') ? (string) ($user->getStripeCustomerId() ?? '') : ''],
            ['Stripe Subscription', method_exists($user,'getStripeSubscriptionId') ? (string) ($user->getStripeSubscriptionId() ?? '') : ''],
            ['Dernier paiement - Date', $lastDate ? $lastDate->format('d/m/Y H:i') : '—'],
            ['Dernier paiement - Montant', ($lastAmountCents !== null) ? number_format($lastAmountCents/100, 2, ',', ' ').' '.$lastCurrency : '—'],
            ['Dernier paiement - Statut', $lastStatus ?? '—'],
            ['Dernier paiement - Référence', $lastRef ?? '—'],
            ['Dernier paiement - Facture (URL)', $lastInvoiceUrl ?? '—'],
        ];

        // ----- PDF si demandé & dompdf dispo -----
        if ($format === 'pdf' && class_exists(\Dompdf\Dompdf::class)) {
            $htmlRows = '';
            foreach ($rows as $line) {
                $htmlRows .= sprintf(
                    '<tr><td style="padding:6px 10px;border:1px solid #ddd;background:#f9f9f9;width:40%%">%s</td><td style="padding:6px 10px;border:1px solid #ddd;">%s</td></tr>',
                    htmlspecialchars((string)$line[0], ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars((string)$line[1], ENT_QUOTES, 'UTF-8')
                );
            }

            $html = '
    <!DOCTYPE html>
    <html lang="fr"><head><meta charset="UTF-8">
    <style>
    body{font-family: DejaVu Sans, Arial, sans-serif; font-size:12px; color:#222;}
    h1{font-size:18px; margin:0 0 10px;}
    .muted{color:#777; font-size:11px;}
    table{border-collapse:collapse; width:100%;}
    </style>
    </head><body>
    <h1>Export utilisateur</h1>
    <div class="muted">Généré le '.(new \DateTime())->format('d/m/Y H:i').'</div>
    <br>
    <table>'.$htmlRows.'</table>
    </body></html>';

            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $pdf = $dompdf->output();

            $fileBase = sprintf('utilisateur_%d_%s', $user->getId(), (new \DateTime())->format('Ymd_His'));
            return new Response(
                $pdf,
                200,
                [
                    'Content-Type'        => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="'.$fileBase.'.pdf"',
                    'Pragma'              => 'no-cache',
                    'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
                ]
            );
        }

        // ----- CSV (par défaut ou fallback si PDF indisponible) -----
        $fileBase = sprintf('utilisateur_%d_%s', $user->getId(), (new \DateTime())->format('Ymd_His'));
        $csv = fopen('php://temp', 'w+');
        fprintf($csv, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

        foreach ($rows as $line) {
            fputcsv($csv, $line, ';');
        }
        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        return new Response(
            $content,
            200,
            [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$fileBase.'.csv"',
                'Pragma'              => 'no-cache',
                'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
            ]
        );
    }
    #[Route('/export/all/{format}', name: 'export_all', methods: ['GET'], requirements: ['format' => 'csv|pdf'])]
    public function exportAll(UserRepository $repo, string $format = 'csv'): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $users = $repo->findAll();

        $secret = $_ENV['STRIPE_SECRET_KEY'] ?? $_SERVER['STRIPE_SECRET_KEY'] ?? null;
        $stripe = null;
        if ($secret) {
            try {
                $stripe = new \Stripe\StripeClient($secret);
            } catch (\Throwable $e) {
                $stripe = null;
            }
        }

        // Rassemble toutes les données à exporter
        $rows = [
            ['Prénom', 'Nom', 'Email', 'Statut abonnement', 'Dernier paiement (date)', 'Dernier paiement (montant)', 'Dernier paiement (statut)']
        ];

        foreach ($users as $user) {
            $subscriptionStatus = method_exists($user, 'getSubscriptionStatus') ? $user->getSubscriptionStatus() : '';
            $lastDate = '—';
            $lastAmount = '—';
            $lastStatus = '—';

            // Récupération Stripe si possible
            if ($stripe && method_exists($user, 'getStripeCustomerId') && $user->getStripeCustomerId()) {
                try {
                    $invoices = $stripe->invoices->all([
                        'customer' => $user->getStripeCustomerId(),
                        'limit' => 1,
                    ]);
                    if (!empty($invoices->data)) {
                        $inv = $invoices->data[0];
                        $paidAt = $inv->status_transitions->paid_at ?? $inv->created ?? null;
                        if ($paidAt) {
                            $lastDate = (new \DateTime('@'.$paidAt))->format('d/m/Y H:i');
                        }
                        $lastAmount = isset($inv->amount_paid)
                            ? number_format($inv->amount_paid / 100, 2, ',', ' ') . ' ' . strtoupper($inv->currency ?? 'EUR')
                            : '—';
                        $lastStatus = $inv->status ?? '—';
                    }
                } catch (\Throwable $e) {
                    // on ignore les erreurs Stripe pour continuer
                }
            }

            $rows[] = [
                (string) ($user->getFirstname() ?? ''),
                (string) ($user->getLastname() ?? ''),
                (string) ($user->getEmail() ?? ''),
                (string) $subscriptionStatus,
                (string) $lastDate,
                (string) $lastAmount,
                (string) $lastStatus,
            ];
        }

        // ========== EXPORT PDF ==========
        if ($format === 'pdf' && class_exists(\Dompdf\Dompdf::class)) {
            $htmlRows = '';
            foreach ($rows as $i => $line) {
                $bg = $i === 0 ? 'background:#f1f1f1;font-weight:bold;' : '';
                $htmlRows .= '<tr style="'.$bg.'">';
                foreach ($line as $cell) {
                    $htmlRows .= '<td style="border:1px solid #ccc;padding:6px 10px;">'.htmlspecialchars((string) $cell, ENT_QUOTES, 'UTF-8').'</td>';
                }
                $htmlRows .= '</tr>';
            }

            $html = '
            <html><head><meta charset="UTF-8">
            <style>
                body{font-family: DejaVu Sans, sans-serif;font-size:12px;color:#333;}
                h1{font-size:18px;margin-bottom:10px;}
                table{border-collapse:collapse;width:100%;margin-top:10px;}
            </style>
            </head><body>
            <h1>Export complet des utilisateurs</h1>
            <table>'.$htmlRows.'</table>
            </body></html>';

            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            $pdf = $dompdf->output();

            $fileBase = sprintf('export_utilisateurs_%s.pdf', (new \DateTime())->format('Ymd_His'));
            return new Response($pdf, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$fileBase.'"',
            ]);
        }

        // ========== EXPORT CSV ==========
        $fileBase = sprintf('export_utilisateurs_%s.csv', (new \DateTime())->format('Ymd_His'));
        $csv = fopen('php://temp', 'w+');
        fprintf($csv, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
        foreach ($rows as $line) {
            fputcsv($csv, $line, ';');
        }
        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        return new Response($content, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileBase.'"',
        ]);
    }

}