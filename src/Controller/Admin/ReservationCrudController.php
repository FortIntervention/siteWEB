<?php

namespace App\Controller\Admin;

use App\Entity\Reservation;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;

class ReservationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Reservation::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Réservation')
            ->setEntityLabelInPlural('Réservations')
            ->setDefaultSort(['date' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            FormField::addFieldset('Détails du Client')->setIcon('fas fa-user'),
            IdField::new('id')->hideOnForm(),
            AssociationField::new('client', 'Client')
                ->setCrudController(UserCrudController::class)
                ->setFormTypeOption('choice_label', 'email'),

            FormField::addFieldset('Informations du Rendez-vous')->setIcon('fas fa-clock'),
            DateTimeField::new('date', 'Date et heure de début'),
            DateTimeField::new('endAt', 'Date et heure de fin'),
            ChoiceField::new('status', 'Statut actuel')->setChoices([
                'En attente' => 'EN_ATTENTE',
                'Validé' => 'VALIDE',
                'Validé (lu)' => 'VALIDE_LU',
                'Annulé' => 'ANNULE',
                'Refusé' => 'REFUSE',
                'Refusé (lu)' => 'REFUSE_LU'
            ])->renderAsBadges([
                'EN_ATTENTE' => 'warning',
                'VALIDE' => 'success',
                'VALIDE_LU' => 'success',
                'ANNULE' => 'danger',
                'REFUSE' => 'dark',
                'REFUSE_LU' => 'dark'
            ]),

            FormField::addFieldset('Détails de l\'Intervention')->setIcon('fas fa-tools'),
            TextField::new('serviceType', 'Type de Prestation'),
            TextField::new('title', 'Objet de la demande'),
            TextField::new('interventionAddress', 'Adresse d\'intervention'),
            TextareaField::new('description', 'Description détaillée')->hideOnIndex(),
        ];
    }
}
