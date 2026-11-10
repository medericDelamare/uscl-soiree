<?php

namespace App\Controller\Admin;

use App\Entity\Booking;
use App\Enum\BookingStatus;
use App\Enum\Meal;
use App\Event\EventInfo;
use App\Repository\BookingRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly EventInfo $event,
    ) {
    }

    public function index(): Response
    {
        $totals = $this->bookings->mealTotals($this->event->childMaxAge);
        $rows = [];
        foreach (Meal::cases() as $meal) {
            $rows[] = ['meal' => $meal, 'adult' => $totals[$meal->value]['adult'] ?? 0, 'child' => $totals[$meal->value]['child'] ?? 0];
        }
        $adults = array_sum(array_column($rows, 'adult'));
        $children = array_sum(array_column($rows, 'child'));

        return $this->render('admin/dashboard.html.twig', [
            'rows' => $rows,
            'adults' => $adults,
            'children' => $children,
            'revenue' => $adults * $this->event->adultPrice + $children * $this->event->childPrice,
            'remarks' => $this->bookings->findGuestsWithComment(),
            'pending' => $this->bookings->count(['status' => BookingStatus::Pending]),
            'taken' => $this->bookings->countTakenSeats($this->event->holdMinutes),
            'latest' => $this->bookings->findBy(['status' => BookingStatus::Paid], ['paidAt' => 'DESC'], 8),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('<img src="/logo-uscl.svg" alt="" style="height:32px;vertical-align:middle;margin-right:8px"> Soirée USCL')
            ->setFaviconPath('/logo-uscl.svg')
            ->setLocales(['fr']);
    }

    public function configureCrud(): Crud
    {
        return Crud::new()
            ->setDateTimeFormat('dd/MM/yyyy HH:mm')
            ->setDateFormat('dd/MM/yyyy')
            ->setTimezone('Europe/Paris')
            ->setPaginatorPageSize(50);
    }

    public function configureAssets(): Assets
    {
        return Assets::new()->addCssFile('admin.css');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-gauge');
        yield MenuItem::section('Soirée');
        yield MenuItem::linkTo(BookingCrudController::class, 'Réservations', 'fa fa-receipt');
        yield MenuItem::linkTo(GuestCrudController::class, 'Convives', 'fa fa-utensils');
        yield MenuItem::linkToRoute('Export Excel', 'fa fa-file-excel', 'admin_export');
        yield MenuItem::section('Réglages');
        yield MenuItem::linkTo(UserCrudController::class, 'Comptes', 'fa fa-user-shield');
        yield MenuItem::linkToRoute('Contrôle à l\'entrée', 'fa fa-qrcode', 'control_home');
        yield MenuItem::linkToRoute('Page de réservation', 'fa fa-store', 'home')->setLinkTarget('_blank');
        yield MenuItem::linkToLogout('Déconnexion', 'fa fa-right-from-bracket');
    }
}
