<?php

namespace App\Controller\Admin;

use App\Entity\Booking;
use App\Enum\BookingStatus;
use App\Payment\PaymentManager;
use App\Form\GuestType;
use App\Service\BookingFactory;
use App\Service\BookingMailer;
use App\Service\CardAssigner;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Les réservations se créent depuis la page publique (le paiement passe par HelloAsso).
 * Le club peut aussi en saisir à la main (paiement reçu en espèces ou par chèque : la réservation
 * est créée directement payée et le billet est envoyé), valider un paiement reçu autrement, ou annuler.
 *
 * @extends AbstractCrudController<Booking>
 */
final class BookingCrudController extends AbstractCrudController
{
    /** Modes de paiement saisissables à la main, et libellés de tous les modes existants. */
    private const MANUAL_PAYMENTS = ['Espèces' => 'especes', 'Chèque' => 'cheque'];
    private const PAYMENT_LABELS = ['especes' => 'Espèces', 'cheque' => 'Chèque', 'manuel' => 'Manuel', 'helloasso' => 'HelloAsso', 'fake' => 'Simulation'];

    public function __construct(
        private readonly BookingFactory $factory,
        private readonly BookingMailer $mailer,
        private readonly CardAssigner $cards,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Booking::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Réservation')->setEntityLabelInPlural('Réservations')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['reference', 'firstName', 'lastName', 'email', 'phone']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $markPaid = Action::new('markPaid', 'Marquer payée (espèces / chèque)', 'fa fa-money-bill')
            ->linkToCrudAction('markPaid')
            ->renderAsForm()
            ->displayIf(fn (Booking $b) => BookingStatus::Paid !== $b->getStatus())
            ->addCssClass('btn btn-success');
        $cancel = Action::new('cancelBooking', 'Annuler', 'fa fa-ban')
            ->linkToCrudAction('cancelBooking')
            ->renderAsForm()
            ->displayIf(fn (Booking $b) => BookingStatus::Cancelled !== $b->getStatus())
            ->addCssClass('text-danger');

        return $actions
            ->disable(Action::EDIT, Action::DELETE)
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $a) => $a->setLabel('Ajouter des invités (espèces / chèque)'))
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $markPaid)
            ->add(Crud::PAGE_DETAIL, $markPaid)
            ->add(Crud::PAGE_INDEX, $cancel)
            ->add(Crud::PAGE_DETAIL, $cancel);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(ChoiceFilter::new('status', 'Statut')->setChoices(array_column(array_map(fn (BookingStatus $s) => [$s->label(), $s], BookingStatus::cases()), 1, 0)));
    }

    public function configureFields(string $pageName): iterable
    {
        $statuses = array_column(array_map(fn (BookingStatus $s) => [$s->label(), $s], BookingStatus::cases()), 1, 0);

        yield TextField::new('reference', 'Référence')->hideOnForm();
        if (Crud::PAGE_NEW === $pageName) {
            yield FormField::addFieldset('Payeur');
            yield TextField::new('firstName', 'Prénom');
            yield TextField::new('lastName', 'Nom');
            yield EmailField::new('email', 'E-mail')->setHelp('Le billet (QR code) est envoyé à cette adresse.');
            yield TextField::new('phone', 'Téléphone');
            yield FormField::addFieldset('Convives (une ligne par place)');
            yield CollectionField::new('guests', 'Convives')
                ->setEntryType(GuestType::class)->allowAdd()->allowDelete()->renderExpanded()
                ->setFormTypeOption('entry_options', ['with_card' => true])
                ->setFormTypeOption('by_reference', false)
                ->setHelp('Le tarif est calculé selon l\'âge. La réservation est enregistrée comme déjà payée. Indiquez le numéro du carton remis à chaque personne pour l\'activer.');
            yield FormField::addFieldset('Paiement');
            yield ChoiceField::new('paymentProvider', 'Reçu en')->setChoices(self::MANUAL_PAYMENTS)->renderExpanded()->setRequired(true);

            return;
        }
        yield TextField::new('fullName', 'Payeur');
        yield EmailField::new('email', 'E-mail');
        yield TextField::new('phone', 'Téléphone');
        yield IntegerField::new('guests.count', 'Places')->formatValue(fn ($v, Booking $b) => $b->getGuests()->count());
        yield MoneyField::new('total', 'Total')->setCurrency('EUR')->setStoredAsCents();
        yield ChoiceField::new('status', 'Statut')->setChoices($statuses)
            ->renderAsBadges([BookingStatus::Paid->value => 'success', BookingStatus::Pending->value => 'warning', BookingStatus::Cancelled->value => 'danger']);
        yield ChoiceField::new('paymentProvider', 'Mode de paiement')->setChoices(array_flip(self::PAYMENT_LABELS))->hideOnIndex();
        yield DateTimeField::new('createdAt', 'Créée le');
        yield DateTimeField::new('paidAt', 'Payée le')->hideOnIndex();
        yield Field::new('guests', 'Convives')->onlyOnDetail()->setTemplatePath('admin/field/guests.html.twig');
    }

    /** Saisie à la main : places vérifiées comme sur le site, puis réservation directement payée et billet envoyé. */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var Booking $booking */
        $booking = $entityInstance;
        $booking->markPaid($booking->getPaymentProvider() ?? 'manuel', null);

        if ([] !== $errors = $this->cards->check($booking)) {
            $this->addFlash('danger', 'Réservation non enregistrée : '.implode(' ', $errors));

            return;
        }

        if (!$this->factory->place($booking)) {
            $this->addFlash('danger', sprintf('Réservation non enregistrée : il ne reste que %d place(s) sur la jauge.', $this->factory->seatsLeft()));

            return;
        }

        $activated = $this->cards->assign($booking);
        $this->mailer->sendConfirmation($booking);
        $this->addFlash('success', sprintf('Réservation %s enregistrée et marquée payée : le billet a été envoyé à %s.%s', $booking->getReference(), $booking->getEmail(), $activated > 0 ? sprintf(' %d carton(s) activé(s).', $activated) : ''));
    }

    #[AdminRoute('/{entityId:booking.id}/marquer-payee', name: 'mark_paid', options: ['methods' => ['POST']])]
    public function markPaid(Booking $booking, EntityManagerInterface $em, BookingMailer $mailer): Response
    {
        if (BookingStatus::Paid !== $booking->getStatus()) {
            $booking->markPaid('manuel', null);
            $em->flush();
            $mailer->sendConfirmation($booking);
            $this->addFlash('success', sprintf('Réservation %s marquée payée : le billet a été envoyé à %s.', $booking->getReference(), $booking->getEmail()));
        }

        return $this->redirectToRoute('admin_booking_index');
    }

    #[AdminRoute('/{entityId:booking.id}/annuler', name: 'cancel', options: ['methods' => ['POST']])]
    public function cancelBooking(Booking $booking, EntityManagerInterface $em): Response
    {
        $booking->setStatus(BookingStatus::Cancelled);
        $this->cards->release($booking);
        $em->flush();
        $this->addFlash('success', 'Réservation '.$booking->getReference().' annulée : ses places sont libérées.');

        return $this->redirectToRoute('admin_booking_index');
    }
}
