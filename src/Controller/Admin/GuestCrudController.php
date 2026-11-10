<?php

namespace App\Controller\Admin;

use App\Entity\Guest;
use App\Enum\BookingStatus;
use App\Enum\Meal;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

/**
 * Listing des convives (payés et en attente de paiement) : lecture seule. Remplace le tableau Excel.
 *
 * @extends AbstractCrudController<Guest>
 */
final class GuestCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Guest::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Convive')->setEntityLabelInPlural('Convives')
            ->setDefaultSort(['lastName' => 'ASC', 'firstName' => 'ASC'])
            ->setSearchFields(['firstName', 'lastName', 'comment', 'booking.reference', 'booking.lastName']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(\EasyCorp\Bundle\EasyAdminBundle\Config\Action::NEW, \EasyCorp\Bundle\EasyAdminBundle\Config\Action::EDIT, \EasyCorp\Bundle\EasyAdminBundle\Config\Action::DELETE, \EasyCorp\Bundle\EasyAdminBundle\Config\Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(ChoiceFilter::new('meal', 'Plat')->setChoices(array_column(array_map(fn (Meal $m) => [$m->label(), $m], Meal::cases()), 1, 0)));
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->join('entity.booking', 'b')->andWhere('b.status IN (:ok)')->setParameter('ok', [BookingStatus::Paid, BookingStatus::Pending]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('lastName', 'Nom');
        yield TextField::new('firstName', 'Prénom');
        yield IntegerField::new('age', 'Âge');
        yield ChoiceField::new('meal', 'Plat')->setChoices(array_column(array_map(fn (Meal $m) => [$m->label(), $m], Meal::cases()), 1, 0));
        yield TextField::new('comment', 'Remarque (régime, allergie)');
        yield BooleanField::new('paid', 'Payé')->renderAsSwitch(false);
        yield TextField::new('booking.reference', 'Réservation');
        yield TextField::new('booking.phone', 'Téléphone (payeur)');
        yield DateTimeField::new('checkedInAt', 'Entré à');
    }
}
