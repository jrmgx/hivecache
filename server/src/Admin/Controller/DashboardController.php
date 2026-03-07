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
        yield MenuItem::linkTo('Users', 'fas fa-list', UserCrudController::class);
        yield MenuItem::linkTo('Admins', 'fas fa-list', AdminCrudController::class);
        yield MenuItem::linkTo('Tags', 'fas fa-list', InstanceTagCrudController::class);
        yield MenuItem::linkTo('Bookmarks', 'fas fa-list', BookmarkCrudController::class);
    }
}
