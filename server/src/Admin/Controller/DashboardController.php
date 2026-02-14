<?php

/** @noinspection PhpRouteMissingInspection */

namespace App\Admin\Controller;

use App\Entity\Admin;
use App\Entity\Bookmark;
use App\Entity\InstanceTag;
use App\Entity\User;
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
        yield MenuItem::linkToCrud('Users', 'fas fa-list', User::class);
        yield MenuItem::linkToCrud('Admins', 'fas fa-list', Admin::class);
        yield MenuItem::linkToCrud('Tags', 'fas fa-list', InstanceTag::class);
        yield MenuItem::linkToCrud('Bookmarks', 'fas fa-list', Bookmark::class);
    }
}
