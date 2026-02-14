<?php

namespace App\Admin\Controller;

use App\Entity\InstanceTag;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<InstanceTag>
 */
class InstanceTagCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return InstanceTag::class;
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
        return $actions
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield TextField::new('name');
        yield TextField::new('slug');
    }
}
