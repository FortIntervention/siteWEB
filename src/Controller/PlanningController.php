<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

class PlanningController extends AbstractController
{
    private string $account = 'fortintervention';

    private array $durations = [
        'Menuiserie' => 45, 'Plomberie' => 60, 'Serrurerie' => 30,
        'Vitrerie' => 90, 'Dépannage Standard' => 60
    ];

    #[Route('/planning', name: 'app_planning', methods: ['GET', 'POST'])]
    public function index(Request $request, ReservationRepository $repo, HttpClientInterface $client, EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        $currentUser = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $token = $this->getParameter('organilog_token');

        if ($request->isMethod('POST')) {
            if (!$currentUser) {
                $this->addFlash('danger', 'Veuillez vous connecter pour réserver.');
                return $this->redirectToRoute('app_login');
            }

            $dateStr = $request->request->get('hidden_date') ?: $request->request->get('date');
            $timeStr = $request->request->get('hidden_time') ?: $request->request->get('time');

            if ($dateStr && $timeStr) {
                try {
                    $start = new \DateTime($dateStr . ' ' . $timeStr);
                    $type = $request->request->get('serviceType');
                    $titre = $request->request->get('title');
                    $adresse = $request->request->get('address');
                    $description = $request->request->get('description');

                    $res = new Reservation();
                    $res->setClient($currentUser)
                        ->setDate($start)
                        ->setEndAt((clone $start)->modify('+' . ($this->durations[$type] ?? 60) . ' minutes'))
                        ->setServiceType($type)
                        ->setTitle($titre)
                        ->setInterventionAddress($adresse)
                        ->setDescription($description)
                        ->setStatus('EN_ATTENTE');

                    $em->persist($res);
                    $em->flush();

                    $telephone = method_exists($currentUser, 'getPhone') ? $currentUser->getPhone() : 'Non renseigné';

                    $emailAdmin = (new TemplatedEmail())
                        ->from(new Address('liptonpeche51@gmail.com', 'Fort Intervention - Site Web'))
                        ->to('fort.intervention@gmail.com')
                        ->subject('🚨 Nouvelle Demande : ' . $type)
                        ->htmlTemplate('emails/notification_admin.html.twig')
                        ->context([
                            'clientPrenom' => $currentUser->getFirstName(),
                            'clientNom'    => $currentUser->getLastName(),
                            'clientTel'    => $telephone,
                            'clientEmail'  => $currentUser->getEmail(),
                            'dateRdv'      => $start,
                            'prestation'   => $type,
                            'titre'        => $titre,
                            'adresse'      => $adresse,
                            'description'  => $description,
                        ]);
                    $mailer->send($emailAdmin);

                    $emailClient = (new TemplatedEmail())
                        ->from(new Address('liptonpeche51@gmail.com', 'Fort Intervention'))
                        ->to($currentUser->getEmail())
                        ->subject('Confirmation de votre demande d\'intervention')
                        ->htmlTemplate('emails/notification_client.html.twig')
                        ->context([
                            'clientNom'  => $currentUser->getFirstName() . ' ' . $currentUser->getLastName(),
                            'prestation' => $type,
                            'dateRdv'    => $start,
                        ]);
                    $mailer->send($emailClient);

                    $this->addFlash('success', 'Votre demande a bien été envoyée ! Un e-mail de confirmation vient de vous être envoyé.');
                    return $this->redirectToRoute('app_planning');

                } catch (\Exception $e) {
                    $this->addFlash('danger', 'Erreur technique : ' . $e->getMessage());
                }
            } else {
                $this->addFlash('danger', 'Veuillez sélectionner une date et une heure valides.');
            }
        }

        $events = [];
        $localValideKeys = [];
        $now = new \DateTime('now');
        $minDate = (clone $now)->modify('-2 months');
        $maxDate = (clone $now)->modify('+6 months');

        $qb = $repo->createQueryBuilder('r')
            ->where('r.status NOT IN (:excluded)')
            ->andWhere('r.date >= :start')
            ->andWhere('r.date <= :end')
            ->setParameter('excluded', ['ANNULE', 'REFUSE', 'REFUSE_LU'])
            ->setParameter('start', $minDate)
            ->setParameter('end', $maxDate);

        foreach ($qb->getQuery()->getResult() as $r) {
            $startObj = clone $r->getDate();
            $isMine = ($currentUser && $r->getClient() === $currentUser);
            $endObj = $r->getEndAt() ? clone $r->getEndAt() : (clone $startObj)->modify('+1 hour');

            $start = $startObj->format('Y-m-d\TH:i:00');
            $end = $endObj->format('Y-m-d\TH:i:00');
            $isValide = in_array($r->getStatus(), ['VALIDE', 'VALIDE_LU']);

            if ($isValide) {
                $localValideKeys[] = $startObj->format('Y-m-d H:i');
            }

            if ($isAdmin) {
                $events[] = [
                    'id' => $r->getId(),
                    'title' => 'SITE: ' . $r->getClient()->getFirstName() . ' ' . $r->getClient()->getLastName(),
                    'start' => $start, 'end' => $end, 'color' => '#6c757d', 'editable' => true
                ];
            } elseif ($isMine) {
                $events[] = [
                    'id' => $r->getId(),
                    'title' => $isValide ? '✅ MON RDV' : '⏳ MA DEMANDE',
                    'start' => $start, 'end' => $end,
                    'color' => $isValide ? '#198754' : '#ffc107',
                    'extendedProps' => ['type' => 'mine']
                ];
            } else {
                $events[] = [
                    'title' => 'Indisponible', 'start' => $start, 'end' => $end,
                    'color' => '#dc3545', 'textColor' => '#ffffff'
                ];
            }
        }

        try {
            $page = 1;
            $maxPages = 3;

            while ($page <= $maxPages) {
                $response = $client->request('POST', 'https://sync.organilog.com/api/v3/intervention/read_all.php', [
                    'timeout' => 8,
                    'json' => [
                        'account' => $this->account, 'token' => $token, 'is_actif' => "1",
                        'limit' => 500, 'order_by' => 'planning_date', 'order_sort' => 'desc', 'page_nbr' => (string)$page
                    ]
                ]);

                $content = $response->toArray(false);
                if (!isset($content['results']) || empty($content['results'])) break;

                foreach ($content['results'] as $inter) {
                    $startStr = trim($inter['planning_hour_start'] ?? '');
                    if (empty($startStr) || str_starts_with($startStr, '00:00')) continue;

                    $startDate = (!empty($inter['planning_date']) && $inter['planning_date'] !== "0000-00-00") ? $inter['planning_date'] : null;
                    if (!$startDate || $startDate < $minDate->format('Y-m-d') || $startDate > $maxDate->format('Y-m-d')) continue;

                    $partsStart = explode(':', $startStr);
                    $timeStart = str_pad($partsStart[0], 2, "0", STR_PAD_LEFT) . ':' . (isset($partsStart[1]) ? str_pad($partsStart[1], 2, "0", STR_PAD_LEFT) : "00");

                    $syncKey = $startDate . ' ' . $timeStart;
                    $keyIndex = array_search($syncKey, $localValideKeys);
                    if ($keyIndex !== false) {
                        unset($localValideKeys[$keyIndex]);
                        continue;
                    }

                    $endStr = trim($inter['planning_hour_end'] ?? '');
                    if (empty($endStr) || str_starts_with($endStr, '00:00')) {
                        $startDt = \DateTime::createFromFormat('H:i', $timeStart);
                        $timeEnd = $startDt ? $startDt->modify('+1 hour')->format('H:i') : '18:00';
                    } else {
                        $partsEnd = explode(':', $endStr);
                        $timeEnd = str_pad($partsEnd[0], 2, "0", STR_PAD_LEFT) . ':' . (isset($partsEnd[1]) ? str_pad($partsEnd[1], 2, "0", STR_PAD_LEFT) : "00");
                    }

                    $events[] = [
                        'title' => $isAdmin ? ($inter['nom'] ?: 'Intervention') : 'Indisponible',
                        'start' => $startDate . 'T' . $timeStart . ':00',
                        'end' => $startDate . 'T' . $timeEnd . ':00',
                        'color' => $isAdmin ? '#0d6efd' : '#dc3545',
                        'textColor' => '#ffffff'
                    ];
                }
                $page++;
            }
        } catch (\Throwable $e) {}

        return $this->render('planning/index.html.twig', ['events' => json_encode($events)]);
    }

    #[Route('/admin/planning/move', name: 'admin_planning_move', methods: ['POST'])]
    public function move(Request $request, ReservationRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $data = json_decode($request->getContent(), true);
        $res = $repo->find($data['id']);
        if ($res) {
            $res->setDate(new \DateTime($data['start']))->setEndAt(new \DateTime($data['end']));
            $em->flush();
            return new JsonResponse(['ok' => true]);
        }
        return new JsonResponse(['ok' => false], 404);
    }

    #[Route('/admin/planning/reset', name: 'admin_planning_reset', methods: ['POST'])]
    public function resetPlanning(ReservationRepository $repo, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        foreach ($repo->findAll() as $res) { $em->remove($res); }
        $em->flush();
        return $this->redirectToRoute('app_planning');
    }

    #[Route('/admin/reservation/{id}/status/{status}', name: 'admin_change_status')]
    public function changeStatus(Reservation $reservation, string $status, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $statutsAutorises = ['VALIDE', 'ANNULE', 'REFUSE'];
        if (in_array($status, $statutsAutorises)) {
            $reservation->setStatus($status);
            $em->flush();
            $this->addFlash('success', 'Le statut a été mis à jour en : ' . $status);
        } else {
            $this->addFlash('danger', 'Statut invalide.');
        }
        return $this->redirectToRoute('app_notifications');
    }
}
