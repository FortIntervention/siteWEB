<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Service\ReservationEmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\String\Slugger\SluggerInterface;

class DashboardController extends AbstractController
{
    private string $account = 'fortintervention';
    #[Route('/admin/demandes', name: 'admin_demandes')]
    public function adminDemandes(ReservationRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $dateStart = (new \DateTime())->modify('-1 week');
        $dateEnd = (new \DateTime())->modify('+2 weeks');

        $qb = $repo->createQueryBuilder('r')
            ->where('r.status IN (:statuses)')
            ->andWhere('r.date >= :start')
            ->andWhere('r.date <= :end')
            ->setParameter('statuses', ['EN_ATTENTE', 'PROPOSITION_CHANGEMENT', 'DEMANDE_DEPLACEMENT'])
            ->setParameter('start', $dateStart)
            ->setParameter('end', $dateEnd)
            ->orderBy('r.date', 'ASC');

        return $this->render('dashboard/admin.html.twig', [
            'demandes' => $qb->getQuery()->getResult()
        ]);
    }

    #[Route('/admin/demande/{id}/action', name: 'admin_demande_action', methods: ['POST'])]
    public function adminAction(Reservation $reservation, Request $request, EntityManagerInterface $em, HttpClientInterface $client, ReservationEmailService $emailService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $action = $request->request->get('action');

        // Récupération sécurisée du token depuis le .env (via services.yaml)
        $token = $this->getParameter('organilog_token');

        if ($action === 'supprimer') {
            $em->remove($reservation);
            $em->flush();
            $this->addFlash('success', 'La demande a bien été supprimée de votre site.');
            return $this->redirectToRoute('admin_demandes');
        }

        if ($action === 'valider') {
            $editedTitle = trim((string)$request->request->get('edited_title')) ?: $reservation->getTitle();
            $editedDescription = trim((string)$request->request->get('edited_description')) ?: $reservation->getDescription();
            $editedAddress = trim((string)$request->request->get('edited_address')) ?: $reservation->getInterventionAddress();

            $editedDate = $request->request->get('edited_date');
            $editedTime = $request->request->get('edited_time');
            if ($editedDate && $editedTime) {
                $newStart = new \DateTime($editedDate . ' ' . $editedTime);
                $reservation->setDate($newStart);
                $reservation->setEndAt((clone $newStart)->modify('+1 hour'));
            }

            $reservation->setTitle($editedTitle);
            $reservation->setDescription($editedDescription);
            $reservation->setInterventionAddress($editedAddress);

            $cp = ''; $ville = ''; $rue = $editedAddress;
            if (preg_match('/^(.*?)\s*(\d{5})\s*(.*)$/', $editedAddress, $matches)) {
                $rue = trim($matches[1], " ,-");
                $cp = trim($matches[2]);
                $ville = trim($matches[3], " ,-");
            }

            $categories = [
                'Menuiserie' => 9893, 'Plomberie' => 9423,
                'Serrurerie' => 9418, 'Vitrerie' => 9428,
                'Dépannage Standard' => 0
            ];
            $idFiliale = $categories[$reservation->getServiceType()] ?? 0;

            try {
                $user = $reservation->getClient();
                $clientTitle = trim($user->getFirstName() . ' ' . $user->getLastName()) ?: 'Client ' . $user->getEmail();

                // 1. Création du Client
                $clientRes = $client->request('POST', 'https://sync.organilog.com/api/v3/client/create.php', [
                    'json' => [
                        'account' => $this->account, 'token' => $token,
                        'data' => [
                            'title' => $clientTitle,
                            'nom' => $user->getLastName(),
                            'prenom' => $user->getFirstName(),
                            'email' => $user->getEmail(),
                            'mobile' => $user->getPhone(),
                            'adresse' => $rue,
                            'code_postal' => $cp,
                            'ville' => $ville,
                            'is_actif' => "1"
                        ]
                    ]
                ]);
                $clientData = $clientRes->toArray(false);
                $fkClientId = $clientData['ID'] ?? $clientData['id'] ?? null;

                if (!$fkClientId) {
                    $this->addFlash('danger', 'Erreur Organilog : Impossible de créer le client.');
                    return $this->redirectToRoute('admin_demandes');
                }

                // 2. Création de l'Adresse
                $fkLocationId = 0;
                $locRes = $client->request('POST', 'https://sync.organilog.com/api/v3/adresse/create.php', [
                    'json' => [
                        'account' => $this->account, 'token' => $token,
                        'data' => [
                            'fk_client_id' => $fkClientId, 'title' => $rue, 'adresse' => $rue,
                            'code_postal' => $cp, 'ville' => $ville,
                            'is_actif' => "1"
                        ]
                    ]
                ]);
                $locData = $locRes->toArray(false);
                $fkLocationId = $locData['ID'] ?? $locData['id'] ?? 0;

                // 3. Création de l'Intervention
                $dateStart = $reservation->getDate();
                $dateEnd = $reservation->getEndAt() ?: (clone $dateStart)->modify('+1 hour');

                $response = $client->request('POST', 'https://sync.organilog.com/api/v3/intervention/create.php', [
                    'json' => [
                        'account' => $this->account, 'token' => $token,
                        'data' => [
                            'nom' => $editedTitle,
                            'fk_client_id' => $fkClientId,
                            'fk_adresse_id' => $fkLocationId,
                            'int_fk_filiale_id' => $idFiliale,
                            'description' => $editedDescription,
                            'is_actif' => "1",
                            'planning_date' => $dateStart->format('Y-m-d'),
                            'planning_hour_start' => $dateStart->format('H:i'),
                            'planning_hour_end' => $dateEnd->format('H:i'),
                        ]
                    ]
                ]);

                $result = $response->toArray(false);

                // Validation finale
                if ($response->getStatusCode() === 200 && (isset($result['ID']) || isset($result['id']))) {
                    $reservation->setStatus('VALIDE');
                    $this->addFlash('popup_success', "Synchronisé avec succès sur Organilog !");
                    $emailService->sendStatusUpdateToClient($reservation);
                } else {
                    $errorMsg = $result['message'] ?? $result['error'] ?? 'Erreur inconnue';
                    $this->addFlash('danger', "Organilog a refusé l'intervention : " . $errorMsg);
                }

            } catch (\Exception $e) {
                $this->addFlash('danger', 'Erreur technique : ' . $e->getMessage());
            }

        } elseif ($action === 'refuser') {
            $reservation->setStatus('REFUSE')->setAdminMessage($request->request->get('message'));
            $this->addFlash('success', 'La demande a été refusée.');
            $emailService->sendStatusUpdateToClient($reservation);
        }

        $em->flush();
        return $this->redirectToRoute('admin_demandes');
    }

    #[Route('/mes-rendez-vous', name: 'app_mes_rdv')]
    public function clientRdv(ReservationRepository $repo): Response
    {
        $user = $this->getUser();
        if (!$user) throw $this->createAccessDeniedException();

        $demandes = $repo->findBy(['client' => $user], ['date' => 'DESC']);
        $events = [];

        foreach ($demandes as $r) {
            if (in_array($r->getStatus(), ['ANNULE', 'REFUSE', 'REFUSE_LU'])) continue;

            $startObj = clone $r->getDate();
            $endObj = $r->getEndAt() ? clone $r->getEndAt() : (clone $startObj)->modify('+1 hour');
            $isValide = in_array($r->getStatus(), ['VALIDE', 'VALIDE_LU']);

            $events[] = [
                'id' => $r->getId(),
                'title' => $isValide ? '✅ ' . $r->getServiceType() : '⏳ ' . $r->getServiceType(),
                'start' => $startObj->format('Y-m-d\TH:i:00'),
                'end' => $endObj->format('Y-m-d\TH:i:00'),
                'color' => $isValide ? '#198754' : '#ffc107',
                'textColor' => '#ffffff'
            ];
        }

        return $this->render('dashboard/mes_rdv.html.twig', [
            'demandes' => $demandes,
            'events' => json_encode($events)
        ]);
    }

    #[Route('/mes-rendez-vous/{id}/reponse', name: 'client_reponse', methods: ['POST'])]
    public function clientReponse(Reservation $reservation, Request $request, EntityManagerInterface $em, ReservationEmailService $emailService): Response
    {
        if ($reservation->getClient() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $action = $request->request->get('action');
        if ($action === 'annuler') {
            $reservation->setStatus('ANNULE');
            $this->addFlash('success', 'Votre rendez-vous a bien été annulé.');

            // On envoie les deux e-mails d'annulation !
            $emailService->sendCancellationToAdmin($reservation);
            $emailService->sendCancellationToClient($reservation);
        }

        $em->flush();
        return $this->redirectToRoute('app_mes_rdv');
    }

    #[Route('/profil', name: 'app_profil', methods: ['GET', 'POST'])]
    public function clientProfil(Request $request, EntityManagerInterface $em, ResetPasswordHelperInterface $resetPasswordHelper, MailerInterface $mailer, SluggerInterface $slugger): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');

            // 1. GESTION DU BOUTON "SAUVEGARDER LES MODIFICATIONS"
            if ($action === 'update_profile') {
                $user->setFirstName($request->request->get('firstName'));
                $user->setLastName($request->request->get('lastName'));
                $user->setEmail($request->request->get('email'));
                $user->setPhone($request->request->get('phone'));

                if (method_exists($user, 'setAddress')) {
                    $user->setAddress($request->request->get('address'));
                }

                $pictureFile = $request->files->get('profilePicture');
                if ($pictureFile) {
                    $originalFilename = pathinfo($pictureFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$pictureFile->guessExtension();

                    try {
                        $pictureFile->move($this->getParameter('kernel.project_dir').'/public/uploads/profiles', $newFilename);
                        $user->setProfilePicture($newFilename);
                    } catch (\Exception $e) {
                        $this->addFlash('danger', 'Erreur lors de l\'upload de l\'image.');
                    }
                }

                $em->flush();
                $this->addFlash('success', 'Votre profil a été mis à jour avec succès.');
                return $this->redirectToRoute('app_profil');
            }

            // 2. GESTION DU BOUTON "M'ENVOYER LE LIEN DE MODIFICATION"
            if ($action === 'send_reset_link') {
                try {
                    $resetToken = $resetPasswordHelper->generateResetToken($user);

                    $email = (new TemplatedEmail())
                        ->from(new Address('liptonpeche51@gmail.com', 'Fort Intervention')) // <-- CORRIGÉ ICI POUR BREVO
                        ->to((string) $user->getEmail())
                        ->subject('Demande de modification de mot de passe')
                        ->htmlTemplate('reset_password/email.html.twig')
                        ->context([
                            'resetToken' => $resetToken,
                        ]);

                    $mailer->send($email);

                    $this->addFlash('success', 'Un lien sécurisé vous a été envoyé par e-mail. Pensez à vérifier vos courriers indésirables (Spams) si vous ne le voyez pas !');

                } catch (\Exception $e) {
                    // Modifié pour afficher la VRAIE erreur si Brevo ou Symfony bloque
                    $this->addFlash('danger', 'Erreur lors de l\'envoi de l\'e-mail : ' . $e->getMessage());
                }

                return $this->redirectToRoute('app_profil');
            }
        }

        return $this->render('dashboard/profil.html.twig', [
            'user' => $user
        ]);
    }

    #[Route('/notifications', name: 'app_notifications')]
    public function clientNotifications(ReservationRepository $repo): Response
    {
        $user = $this->getUser();
        if (!$user) throw $this->createAccessDeniedException();

        if ($this->isGranted('ROLE_ADMIN')) {
            $demandes = $repo->findBy(['status' => 'EN_ATTENTE'], ['date' => 'ASC']);
        } else {
            $demandes = $repo->findBy(['client' => $user], ['date' => 'DESC']);
        }

        return $this->render('dashboard/notifications.html.twig', [
            'demandes' => $demandes
        ]);
    }

    #[Route('/notifications/tout-lu', name: 'client_tout_lu', methods: ['POST'])]
    public function clientToutLu(ReservationRepository $repo, EntityManagerInterface $em): Response
    {
        foreach ($repo->findBy(['client' => $this->getUser(), 'status' => ['VALIDE', 'REFUSE']]) as $d) {
            $d->setStatus($d->getStatus() === 'VALIDE' ? 'VALIDE_LU' : 'REFUSE_LU');
        }
        $em->flush();
        return $this->redirectToRoute('app_notifications');
    }

    #[Route('/user/notifications/count', name: 'user_notif_count')]
    public function userNotificationBadge(ReservationRepository $repo): Response
    {
        $user = $this->getUser();
        if (!$user || $this->isGranted('ROLE_ADMIN')) return new Response('0');
        return new Response((string)$repo->count(['client' => $user, 'status' => ['VALIDE', 'REFUSE']]));
    }

    #[Route('/admin/notifications/count', name: 'admin_notif_count')]
    public function adminNotificationBadge(ReservationRepository $repo): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) return new Response('0');
        return new Response((string)$repo->count(['status' => 'EN_ATTENTE']));
    }

    #[Route('/admin/demandes/reset', name: 'admin_demandes_reset', methods: ['POST'])]
    public function resetPlanningDatabase(ReservationRepository $repo, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $toutesLesDemandes = $repo->findAll();
        foreach ($toutesLesDemandes as $demande) {
            $em->remove($demande);
        }
        $em->flush();
        $this->addFlash('success', 'Le planning a été entièrement vidé et remis à zéro !');
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
