<?php

namespace App\Service;

use App\Entity\Reservation;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class ReservationEmailService
{
    public function __construct(private MailerInterface $mailer) {}

    public function sendNotificationToAdmin(Reservation $res)
    {
        $email = (new TemplatedEmail())
            // UPDATED FROM ADDRESS
            ->from(new Address('liptonpeche51@gmail.com', 'Site Web Fort Intervention'))
            ->to('fort.intervention@gmail.com')
            ->subject('🚨 Nouvelle Demande : ' . $res->getServiceType())
            ->htmlTemplate('emails/admin_new_reservation.html.twig')
            ->context(['res' => $res]);

        $this->mailer->send($email);
    }

    public function sendStatusUpdateToClient(Reservation $res)
    {
        $statusLabels = [
            'VALIDE' => 'Confirmée ✅',
            'REFUSE' => 'Refusée ❌',
            'ANNULE' => 'Annulée ⚠️',
            'PROPOSITION_CHANGEMENT' => 'Modifiée / Décalée 📅'
        ];

        $subject = 'Mise à jour de votre intervention : ' . ($statusLabels[$res->getStatus()] ?? $res->getStatus());

        $email = (new TemplatedEmail())
            // UPDATED FROM ADDRESS
            ->from(new Address('liptonpeche51@gmail.com', 'Fort Intervention'))
            ->to($res->getClient()->getEmail())
            ->subject($subject)
            ->htmlTemplate('emails/client_status_update.html.twig')
            ->context(['res' => $res, 'statusLabel' => $statusLabels[$res->getStatus()] ?? $res->getStatus()]);

        $this->mailer->send($email);
    }

    // Mail envoyé à l'ADMIN quand un client annule
    public function sendCancellationToAdmin(Reservation $res)
    {
        $email = (new TemplatedEmail())
            // UPDATED FROM ADDRESS
            ->from(new Address('liptonpeche51@gmail.com', 'Fort Intervention - Site Web'))
            ->to('fort.intervention@gmail.com')
            ->subject('❌ Annulation de RDV : ' . $res->getServiceType())
            ->htmlTemplate('emails/admin_cancellation.html.twig')
            ->context(['res' => $res]);

        $this->mailer->send($email);
    }

    // Mail envoyé au CLIENT pour confirmer son annulation
    public function sendCancellationToClient(Reservation $res)
    {
        $email = (new TemplatedEmail())
            // UPDATED FROM ADDRESS
            ->from(new Address('liptonpeche51@gmail.com', 'Fort Intervention'))
            ->to((string) $res->getClient()->getEmail())
            ->subject('Confirmation d\'annulation de votre rendez-vous')
            ->htmlTemplate('emails/client_cancellation.html.twig')
            ->context(['res' => $res]);

        $this->mailer->send($email);
    }
}
