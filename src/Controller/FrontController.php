<?php

namespace App\Controller;

use App\Repository\RealisationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FrontController extends AbstractController
{
    #[Route('/menuiserie', name: 'app_menuiserie')]
    public function menuiserie(RealisationRepository $repo): Response
    {
        return $this->render('services/menuiserie.html.twig', [
            'realisations' => $repo->findBy(['category' => 'Menuiserie'], ['createdAt' => 'DESC'])
        ]);
    }

    #[Route('/vitrerie', name: 'app_service_vitrerie')]
    public function vitrerie(RealisationRepository $repo): Response
    {
        return $this->render('services/vitrerie.html.twig', [
            'realisations' => $repo->findBy(['category' => 'Vitrerie'], ['createdAt' => 'DESC'])
        ]);
    }

    #[Route('/serrurerie', name: 'app_service_serrurerie')]
    public function serrurerie(RealisationRepository $repo): Response
    {
        return $this->render('services/serrurerie.html.twig', [
            'realisations' => $repo->findBy(['category' => 'Serrurerie'], ['createdAt' => 'DESC'])
        ]);
    }

    #[Route('/plomberie', name: 'app_service_plomberie')]
    public function plomberie(RealisationRepository $repo): Response
    {
        return $this->render('services/plomberie.html.twig', [
            'realisations' => $repo->findBy(['category' => 'Plomberie'], ['createdAt' => 'DESC'])
        ]);
    }

    #[Route('/mentions-legales', name: 'app_mentions_legales')]
    public function mentionsLegales(): Response
    {
        return $this->render('legal/mentions_legales.html.twig');
    }

    #[Route('/cgv', name: 'app_cgv')]
    public function cgv(): Response
    {
        return $this->render('legal/cgv.html.twig');
    }

    #[Route('/cookies', name: 'app_cookies')]
    public function cookies(): Response
    {
        return $this->render('legal/cookies.html.twig');
    }

    #[Route('/a-propos', name: 'app_about')]
    public function about(): Response
    {
        return $this->render('legal/about.html.twig');
    }
}
