<?php

namespace App\Controller\Admin;

use App\Entity\Realisation;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class RealisationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Realisation::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Réalisation')
            ->setEntityLabelInPlural('Réalisations')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPageTitle('index', 'Galerie des interventions');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            FormField::addFieldset('Informations Générales')->setIcon('fas fa-info-circle'),
            IdField::new('id')->hideOnForm(),
            TextField::new('title', 'Titre de l\'intervention'),
            ChoiceField::new('category', 'Endroit / Page du site')->setChoices([
                'Serrurerie' => 'Serrurerie',
                'Menuiserie' => 'Menuiserie',
                'Plomberie' => 'Plomberie',
                'Vitrerie' => 'Vitrerie'
            ]),
            TextField::new('subCategory', 'Sous-catégorie')->setRequired(false),
            TextareaField::new('description', 'Description détaillée')->hideOnIndex(),
            DateTimeField::new('createdAt', 'Date d\'ajout')->hideOnForm(),

            FormField::addFieldset('Médias (Avant / Après)')->setIcon('fas fa-images'),
            BooleanField::new('isVideo', 'Afficher en tant que vidéo ?'),
            ImageField::new('imageBefore', 'Fichier AVANT')
                ->setBasePath('uploads/realisations')
                ->setUploadDir('public/uploads/realisations')
                ->setUploadedFileNamePattern('[randomhash].[extension]')
                ->setRequired(false),
            ImageField::new('imageAfter', 'Fichier APRÈS (ou Résultat final)')
                ->setBasePath('uploads/realisations')
                ->setUploadDir('public/uploads/realisations')
                ->setUploadedFileNamePattern('[randomhash].[extension]')
                ->setRequired($pageName === Crud::PAGE_NEW),
        ];
    }
}
