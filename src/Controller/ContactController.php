<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact')]
    public function index(Request $request): Response
    {
        // Si le formulaire est soumis
        if ($request->isMethod('POST')) {
            // Ici, tu pourrais envoyer un emails ou enregistrer en BDD
            $this->addFlash('success', 'Votre demande a bien été envoyée ! Nous vous rappellerons rapidement.');
            return $this->redirectToRoute('app_planning');
        }

        return $this->render('contact/menuiserie.html.twig');
    }
}
