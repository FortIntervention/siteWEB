<?php

namespace App\Controller;

use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('/admin/reservations', name: 'admin_resas')]
    public function list(ReservationRepository $repo): Response
    {
        return $this->render('admin/resas.html.twig', [
            'resas' => $repo->findBy([], ['date' => 'DESC'])
        ]);
    }

    #[Route('/admin/valider/{id}', name: 'admin_valider')]
    public function valider(int $id, ReservationRepository $repo, EntityManagerInterface $em): Response
    {
        $res = $repo->find($id);
        if ($res) {
            $res->setStatus('VALIDE');
            $em->flush();
        }
        return $this->redirectToRoute('admin_resas');
    }

    #[Route('/admin/supprimer/{id}', name: 'admin_supprimer')]
    public function supprimer(int $id, ReservationRepository $repo, EntityManagerInterface $em): Response
    {
        $res = $repo->find($id);
        if ($res) {
            $em->remove($res);
            $em->flush();
        }
        return $this->redirectToRoute('admin_resas');
    }
}
