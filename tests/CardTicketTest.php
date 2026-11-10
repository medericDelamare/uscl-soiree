<?php

namespace App\Tests;

use App\Entity\Booking;
use App\Entity\Guest;
use App\Entity\Ticket;
use App\Entity\User;
use App\Enum\BookingStatus;
use App\Enum\Meal;
use App\Repository\TicketRepository;
use App\Service\CardAssigner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Cartons numérotés (un par personne) : attribution à la saisie, contrôle à l'entrée. */
final class CardTicketTest extends WebTestCase
{
    public function testUnassignedCardIsRefusedAtTheDoor(): void
    {
        $client = static::createClient();
        $this->createTicket($client, 7);
        $this->loginController($client);

        $client->request('GET', '/controle/'.$this->token($client, 7));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.verdict--ko', 'Carton non attribué');
        self::assertSelectorNotExists('button');
    }

    public function testAssignedCardIsValidatedOnceThenWarns(): void
    {
        $client = static::createClient();
        $guest = $this->paidGuest($client, 'Léa', 'Durand');
        $this->assign($client, 12, $guest);
        $this->loginController($client);
        $token = $this->token($client, 12);

        $crawler = $client->request('GET', '/controle/'.$token);
        self::assertSelectorTextContains('.verdict--ok', 'Carton valable');
        self::assertSelectorTextContains('.entry', 'Léa DURAND');
        $client->submit($crawler->selectButton('Valider l\'entrée')->form());
        self::assertResponseRedirects('/controle/'.$token);
        $client->followRedirect();
        self::assertSelectorTextContains('.verdict--warn', 'Déjà entré');

        // Annulation d'une validation faite par erreur.
        $client->submit($client->getCrawler()->selectButton('Annuler')->form());
        $client->followRedirect();
        self::assertSelectorTextContains('.verdict--ok', 'Carton valable');
    }

    public function testCardNumberTypedInSearchOpensThePerson(): void
    {
        $client = static::createClient();
        $guest = $this->paidGuest($client, 'Hugo', 'Petit');
        $this->assign($client, 42, $guest);
        $this->loginController($client);

        $client->request('GET', '/controle?q=42');
        self::assertResponseRedirects('/controle/'.$this->token($client, 42));
        $client->request('GET', '/controle?q=999'); // numéro inconnu : simple recherche, sans erreur
        self::assertResponseIsSuccessful();
    }

    public function testCardOfCancelledBookingIsRefusedAndReleased(): void
    {
        $client = static::createClient();
        $guest = $this->paidGuest($client, 'Eva', 'Roux');
        $this->assign($client, 5, $guest);
        $this->loginController($client);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $booking = $guest->getBooking();
        $booking->setStatus(BookingStatus::Cancelled);
        $em->flush();
        $client->request('GET', '/controle/'.$this->token($client, 5));
        self::assertSelectorTextContains('.verdict--ko', 'Billet non valable');

        static::getContainer()->get(CardAssigner::class)->release($booking);
        $em->clear();
        self::assertFalse($em->getRepository(Ticket::class)->findOneByNumber(5)->isActive(), 'le carton redevient disponible');
    }

    public function testBookingQrStillOpensTheBooking(): void
    {
        $client = static::createClient();
        $guest = $this->paidGuest($client, 'Noé', 'Blanc');
        $this->loginController($client);

        $client->request('GET', '/controle/'.$guest->getBooking()->getAccessToken());
        self::assertSelectorTextContains('.verdict--ok', 'Billet valable');
        $client->request('GET', '/controle/'.str_repeat('0', 32));
        self::assertResponseStatusCodeSame(404);
    }

    public function testAssignerRejectsUnknownDuplicateAndTakenNumbers(): void
    {
        $client = static::createClient();
        $taken = $this->paidGuest($client, 'Ana', 'Lopez');
        $this->assign($client, 3, $taken);
        $this->createTicket($client, 4);
        $assigner = static::getContainer()->get(CardAssigner::class);

        $booking = $this->unsavedBooking([3, 999, 4, 4]);
        $errors = implode(' | ', $assigner->check($booking));
        self::assertStringContainsString('n° 3 est déjà attribué', $errors);
        self::assertStringContainsString('n° 999 n\'existe pas', $errors);
        self::assertStringContainsString('n° 4 est saisi deux fois', $errors);

        $ok = $this->unsavedBooking([4, null]);
        self::assertSame([], $assigner->check($ok));
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($ok);
        $em->flush();
        self::assertSame(1, $assigner->assign($ok));
        self::assertTrue($em->getRepository(Ticket::class)->findOneByNumber(4)->isActive());
    }

    public function testConfirmationEmailShowsParisTimeEvenWhenPhpIsInUtc(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('UTC'); // comme chez l'hébergeur
        try {
            static::bootKernel();
            $html = static::getContainer()->get('twig')->render('emails/confirmation.html.twig', [
                'booking' => ['firstName' => 'Marie', 'reference' => 'SOI-TEST', 'total' => 2400, 'accessToken' => str_repeat('a', 32),
                    'guests' => [['fullName' => 'Marie Dupont', 'age' => 38, 'comment' => '', 'meal' => ['label' => 'Lasagnes & salade']]]],
                'qr_cid' => 'billet.png',
            ]);
            self::assertStringContainsString('20h00', $html);
            self::assertStringNotContainsString('19h00', $html);
        } finally {
            date_default_timezone_set($previous);
        }
    }


    public function testAdminManualBookingActivatesTheCardsAndRefusesBadNumbers(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->createTicket($client, 10);
        $this->createTicket($client, 11);
        $em->persist($admin = (new User())->setEmail('admin@example.com')->setName('Admin')->setAdmin(true)->setPassword('x'));
        $em->flush();
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/admin/booking/new');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="Booking"]');
        self::assertSelectorExists('[data-prototype]', 'la liste des convives est présente');
        self::assertStringContainsString('N° du carton remis', (string) $form->filter('[data-prototype]')->attr('data-prototype'));

        $token = $form->filter('input[name="Booking[_token]"]')->attr('value');
        $payload = fn (array $cards) => ['Booking' => [
            'firstName' => 'Paul', 'lastName' => 'Martin', 'email' => 'paul@example.com', 'phone' => '0102030405', 'paymentProvider' => 'cheque',
            'guests' => array_map(fn ($c, $i) => ['firstName' => 'Inv'.$i, 'lastName' => 'Martin', 'age' => '30', 'meal' => 'lasagne', 'comment' => '', 'cardNumber' => (string) $c], $cards, array_keys($cards)),
            '_token' => $token,
        ]];
        $action = $client->getRequest()->getUri();

        // Un numéro inconnu bloque tout l'enregistrement.
        $client->request('POST', $action, $payload([10, 999]));
        $em->clear();
        self::assertSame(0, $em->getRepository(Booking::class)->count([]), 'rien n\'est enregistré');
        self::assertFalse($em->getRepository(Ticket::class)->findOneByNumber(10)->isActive());

        // Numéros valides : réservation payée, cartons activés, billet envoyé.
        $client->request('POST', $action, $payload([10, 11]));
        $em->clear();
        self::assertSame(1, $em->getRepository(Booking::class)->count([]));
        self::assertTrue($em->getRepository(Ticket::class)->findOneByNumber(10)->isActive());
        self::assertTrue($em->getRepository(Ticket::class)->findOneByNumber(11)->isActive());
        self::assertEmailCount(2);
    }

    /** @param list<int|null> $numbers */
    private function unsavedBooking(array $numbers): Booking
    {
        $booking = (new Booking())->setFirstName('P')->setLastName('Q')->setEmail('p@q.fr')->setPhone('0102030405');
        foreach ($numbers as $i => $n) {
            $booking->addGuest((new Guest())->setFirstName('G')->setLastName((string) $i)->setAge(30)->setMeal(Meal::Lasagne)->setPrice(2400)->setCardNumber($n));
        }
        $booking->markPaid('especes', null);

        return $booking;
    }

    private function paidGuest(KernelBrowser $client, string $first, string $last): Guest
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $booking = (new Booking())->setFirstName($first)->setLastName($last)->setEmail('x@example.com')->setPhone('0102030405');
        $booking->addGuest($guest = (new Guest())->setFirstName($first)->setLastName($last)->setAge(30)->setMeal(Meal::AssietteAnglaise)->setPrice(2400));
        $booking->markPaid('especes', null);
        $em->persist($booking);
        $em->flush();

        return $guest;
    }

    private function createTicket(KernelBrowser $client, int $number): Ticket
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($ticket = new Ticket($number));
        $em->flush();

        return $ticket;
    }

    private function assign(KernelBrowser $client, int $number, Guest $guest): void
    {
        $this->createTicket($client, $number)->attachTo($guest);
        static::getContainer()->get(EntityManagerInterface::class)->flush();
    }

    private function token(KernelBrowser $client, int $number): string
    {
        return static::getContainer()->get(TicketRepository::class)->findOneByNumber($number)->getToken();
    }

    private function loginController(KernelBrowser $client): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user = (new User())->setEmail('entree@example.com')->setName('Entrée')->setPassword('x'));
        $em->flush();
        $client->loginUser($user);
    }
}
