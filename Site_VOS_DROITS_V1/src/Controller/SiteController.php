<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\ContactType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class SiteController extends AbstractController
{
#[Route('/', name: 'home', methods: ['GET','POST'])]
public function home(Request $request, EntityManagerInterface $em): Response
{
    $contact = new Contact();
    $form = $this->createForm(ContactType::class, $contact, [
        'attr'   => ['class' => 'contact-form', 'novalidate' => 'novalidate', 'id' => 'contact-form'],
        'action' => $this->generateUrl('home'),
        'method' => 'POST',
    ]);

    $form->handleRequest($request);

    // -- Soumission AJAX → on répond en JSON
    $isAjax = $request->headers->get('X-Requested-With') === 'XMLHttpRequest'
           || str_contains((string) $request->headers->get('Accept'), 'application/json');

    if ($form->isSubmitted()) {

        if ($form->isValid()) {
            $contact->setCreatedAt(new \DateTimeImmutable());
            $em->persist($contact);
            $em->flush();

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
    return $this->render('site/home.html.twig', [
        'form' => $form->createView(),
    ]);
}



    #[Route('/abonnement', name: 'subscription')]
    public function subscription(): Response
    {
        return $this->render('site/abonnement.html.twig');
    }

    #[Route('/cgv', name: 'cgv')]
    public function cgv(): Response
    {
        return $this->render('site/cgv.html.twig');
    }
}
