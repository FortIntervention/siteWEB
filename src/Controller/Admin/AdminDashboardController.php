<?php

namespace App\Controller\Admin;

use App\Controller\Admin\ReservationCrudController;
use App\Controller\Admin\UserCrudController;
use App\Controller\Admin\RealisationCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

#[AdminDashboard(routePath: '/admin/gestion', routeName: 'admin')]
class AdminDashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
        return $this->redirect(
            $adminUrlGenerator
                ->unsetAll()
                ->setController(ReservationCrudController::class)
                ->generateUrl()
        );
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('<span class="text-primary fw-bold">Fort</span> Intervention')
            ->setFaviconPath('images/FortFav.jpg')
            ->disableDarkMode();
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addCssFile('css/Saas.css');
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        return parent::configureUserMenu($user)
            ->setName($user->getFirstName() . ' ' . $user->getLastName())
            ->setAvatarUrl('uploads/profiles/' . $user->getProfilePicture());
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToRoute('Retour au site', 'fa fa-arrow-left', 'app_home');

        yield MenuItem::section('Gestion des Interventions');
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);

        $reservationUrl = $adminUrlGenerator->unsetAll()->setController(ReservationCrudController::class)->generateUrl();
        yield MenuItem::linkToUrl('Réservations', 'fas fa-calendar-check', $reservationUrl);

        yield MenuItem::section('Contenu du site');
        $realisationUrl = $adminUrlGenerator->unsetAll()->setController(RealisationCrudController::class)->generateUrl();
        yield MenuItem::linkToUrl('Images Avant / Après', 'fas fa-images', $realisationUrl);

        yield MenuItem::section('Administration');
        $userUrl = $adminUrlGenerator->unsetAll()->setController(UserCrudController::class)->generateUrl();
        yield MenuItem::linkToUrl('Clients & Utilisateurs', 'fas fa-users', $userUrl);
    }
}
