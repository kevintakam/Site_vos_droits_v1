<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\ContactType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\SubscriptionRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;


final class SiteController extends AbstractController
{
    #[Route('/', name: 'home', methods: ['GET','POST'])]
    public function home(Request $request, EntityManagerInterface $em, Security $sec, SubscriptionRepository $subsRepo,MailerInterface $mailer  ): Response
    {
        $contact = new Contact();
        $form = $this->createForm(ContactType::class, $contact, [
            'attr'   => ['class' => 'contact-form', 'novalidate' => 'novalidate', 'id' => 'contact-form'],
            'action' => $this->generateUrl('home'),
            'method' => 'POST',
        ]);

        $form->handleRequest($request);
        $isAjax = $request->headers->get('X-Requested-With') === 'XMLHttpRequest'
            || str_contains((string) $request->headers->get('Accept'), 'application/json');
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $contact->setCreatedAt(new \DateTimeImmutable());
                $em->persist($contact);
                $em->flush();
                            // ===== ENVOI EMAILS =====
            $fullName = trim(($contact->getFirstName() ?? '').' '.($contact->getLastName() ?? '')) ?: ($contact->getEmail() ?? 'Contact');
            try {
                // 1) Notification admin
                $adminEmail = (new TemplatedEmail())
                    ->from(new Address($_ENV['APP_FROM_EMAIL'] ?? 'no-reply@example.com', $_ENV['APP_FROM_NAME'] ?? 'Association'))
                    ->to(new Address($_ENV['APP_CONTACT_TO'] ?? ($_ENV['APP_FROM_EMAIL'] ?? 'admin@example.com')))
                    ->replyTo(new Address($contact->getEmail() ?? $_ENV['APP_FROM_EMAIL'], $fullName))
                    ->subject('Nouveau message de contact')
                    ->htmlTemplate('emails/contact_admin.html.twig')
                    ->context([
                        'c' => $contact,   
                    ]);

                $mailer->send($adminEmail);
                if ($contact->getEmail()) {
                    $userEmail = (new TemplatedEmail())
                        ->from(new Address($_ENV['APP_FROM_EMAIL'] ?? 'no-reply@example.com', $_ENV['APP_FROM_NAME'] ?? 'Association'))
                        ->to(new Address($contact->getEmail(), $fullName))
                        ->subject('Votre message a bien été reçu')
                        ->htmlTemplate('emails/contact_user.html.twig')
                        ->context([
                            'c' => $contact,
                        ]);

                    $mailer->send($userEmail);
                }
            } catch (TransportExceptionInterface $e) {
                if ($isAjax) {
                    return $this->json([
                        'ok'      => true,
                        'message' => '✅ Formulaire enregistré. ⚠️ L’email de confirmation n’a pas pu être envoyé pour le moment.',
                    ], 201);
                }
                $this->addFlash('warning', 'Votre message est enregistré, mais l’e-mail n’a pas pu être envoyé pour le moment.');
                return $this->redirectToRoute('home', ['_fragment' => 'contact']);
            }
                if ($isAjax) {
                    return $this->json([
                        'ok'      => true,
                        'message' => '✅ Votre formulaire a bien été soumis.',
                    ], 201);
                }

                $this->addFlash('success', '✅ Votre formulaire a bien été soumis.');
                return $this->redirectToRoute('home', ['_fragment' => 'contact']);
            }
            $errors = ['_global' => [], 'fields' => []];

            foreach ($form->getErrors(true, true) as $error) {
                $origin = $error->getOrigin();
                $name   = $origin?->getName();
                if ($origin === null || $origin->isRoot() || $name === null) {
                    $errors['_global'][] = $error->getMessage();
                } else {
                    $errors['fields'][$name][] = $error->getMessage();
                }
            }

            if ($isAjax) {
                return $this->json([
                    'ok'      => false,
                    'message' => '⚠️ Le formulaire n’a pas été correctement rempli.',
                    'errors'  => $errors,
                ], 422);
            }
            $this->addFlash('error', '⚠️ Le formulaire n’a pas été correctement rempli.');
        }
               /** @var \App\Entity\User|null $user */
        $user = $sec->getUser();
        $sub = $user ? $subsRepo->findOneBy(['user' => $user], ['id' => 'DESC']) : null;

        return $this->render('site/home.html.twig', [
            'form' => $form->createView(),
            'hasActiveSubscription' => $sub?->isActive() ?? false,
            'subscription' => $sub,


        ]);
    }


    #[Route('/cgv', name: 'cgv')]
    public function cgv(): Response
    {
        return $this->render('site/cgv.html.twig');
    }
}
