<?php

/** @noinspection PhpRouteMissingInspection */

namespace App\Admin\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->redirectToRoute('admin_user_index');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('HiveCache')
        ;
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkTo(UserCrudController::class, 'Users', 'fas fa-list')->setAction('index');
        yield MenuItem::linkTo(AdminCrudController::class, 'Admins', 'fas fa-list')->setAction('index');
        yield MenuItem::linkTo(InstanceTagCrudController::class, 'Tags', 'fas fa-list')->setAction('index');
        yield MenuItem::linkTo(BookmarkCrudController::class, 'Bookmarks', 'fas fa-list')->setAction('index');
    }
}
