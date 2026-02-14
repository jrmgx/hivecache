<?php

namespace App\Admin\Controller;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<User>
 */
class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    //    public function configureCrud(Crud $crud): Crud
    //    {
    //        return $crud
    //            ->setPageTitle(Crud::PAGE_EDIT, 'Users')
    //            ->setEntityLabelInPlural('Users')
    //            ->setEntityLabelInSingular('User')
    //        ;
    //    }

    public function configureActions(Actions $actions): Actions
    {
        // TODO add rotate security action (global and user based)
        return $actions
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield TextField::new('username');
        yield BooleanField::new('isPublic');
    }
}
