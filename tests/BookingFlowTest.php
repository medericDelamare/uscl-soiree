<?php

namespace App\Tests;

use App\Entity\Booking;
use App\Entity\Guest;
use App\Entity\User;
use App\Enum\BookingStatus;
use App\Enum\Meal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BookingFlowTest extends WebTestCase
{
    public function testHomePageShowsPricesAndSeats(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.seats', '230 places restantes');
        self::assertSelectorTextContains('.hero', '24');
    }

    public function testFullBookingAndPayment(): void
    {
        $client = static::createClient();
        $this->post($client, [['Jeanne', 'Martin', 40, 'lasagne', 'Végétarienne'], ['Léo', 'Martin', 9, 'assiette_anglaise']]);
        self::assertResponseRedirects();
        $client->followRedirect(); // page de simulation
        self::assertSelectorTextContains('h1', 'Simulation de paiement');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $booking = $em->getRepository(Booking::class)->findOneBy([]);
        self::assertSame(3700, $booking->getTotal());
        self::assertSame(BookingStatus::Pending, $booking->getStatus());

        $client->submitForm('Payer');
        self::assertResponseRedirects('/paiement/retour/'.$booking->getAccessToken());
        $client->followRedirect(); // retour de paiement : confirmation + e-mails (billet au payeur, notification au club)
        self::assertEmailCount(2);
        self::assertResponseRedirects('/reservation/'.$booking->getAccessToken());
        $client->followRedirect();
        self::assertSelectorExists('.ticket img');
        self::assertSelectorTextContains('.badge--ok', 'Payée');
        self::assertSelectorTextContains('.table', 'Remarque : Végétarienne');
        $em->clear();
        self::assertSame(BookingStatus::Paid, $em->getRepository(Booking::class)->findOneBy([])->getStatus());
    }

    public function testBookingRefusedBeyondCapacity(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $full = new Booking();
        $full->setFirstName('A')->setLastName('B')->setEmail('a@b.fr')->setPhone('0102030405');
        for ($i = 0; $i < 229; ++$i) {
            $full->addGuest((new Guest())->setFirstName('G')->setLastName((string) $i)->setAge(30)->setMeal(Meal::Lasagne)->setPrice(2200));
        }
        $full->markPaid('manuel', null);
        $em->persist($full);
        $em->flush();

        $this->post($client, [['Jeanne', 'Martin', 40, 'lasagne'], ['Paul', 'Martin', 41, 'lasagne']]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alert--warning', 'Il ne reste que 1 place');
        self::assertSame(1, $em->getRepository(Booking::class)->count([]));
    }

    public function testValidationErrors(): void
    {
        $client = static::createClient();
        $this->post($client, [['', 'Martin', 40, 'lasagne']]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Indiquez le prénom.');
        self::assertSame(0, static::getContainer()->get(EntityManagerInterface::class)->getRepository(Booking::class)->count([]));
    }

    public function testControlRequiresLoginAndChecksIn(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $booking = new Booking();
        $booking->setFirstName('A')->setLastName('B')->setEmail('a@b.fr')->setPhone('0102030405');
        $booking->addGuest((new Guest())->setFirstName('G')->setLastName('H')->setAge(30)->setMeal(Meal::Lasagne)->setPrice(2200));
        $booking->markPaid('manuel', null);
        $user = (new User())->setEmail('entree@example.com')->setName('Entrée')->setPassword('x');
        $em->persist($booking);
        $em->persist($user);
        $em->flush();

        $client->request('GET', '/controle/'.$booking->getAccessToken());
        self::assertResponseRedirects('/connexion');

        $client->loginUser($user);
        $client->request('GET', '/controle/'.$booking->getAccessToken());
        self::assertSelectorTextContains('.verdict--ok', 'Billet valable');
        $client->submitForm("Valider l'entrée");
        $client->followRedirect();
        self::assertSelectorTextContains('.verdict--warn', 'Déjà entrés');

        $client->request('GET', '/admin');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminListingIncludesPendingAndExports(): void
    {
        $client = static::createClient();
        $this->post($client, [['Jeanne', 'Martin', 40, 'lasagne']]); // en attente de paiement
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $admin = (new User())->setEmail('admin@example.com')->setName('Admin')->setAdmin(true)->setPassword('x');
        $em->persist($admin);
        $em->flush();
        $client->loginUser($admin);

        $client->request('GET', '/admin/guest');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Martin');

        $client->request('GET', '/admin/export');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('spreadsheetml', $client->getResponse()->headers->get('Content-Type'));
    }

    public function testRegistrationClosesAfterDeadline(): void
    {
        $client = static::createClient();
        $event = static::getContainer()->get(\App\Event\EventInfo::class);
        self::assertTrue($event->isOpen(new \DateTimeImmutable('2027-01-01 23:00', new \DateTimeZone('Europe/Paris'))));
        self::assertFalse($event->isOpen(new \DateTimeImmutable('2027-01-02 00:01', new \DateTimeZone('Europe/Paris'))));
    }

    private function post(KernelBrowser $client, array $guests): void
    {
        $crawler = $client->request('GET', '/');
        $token = $crawler->filter('input[name="booking[_token]"]')->attr('value');
        $client->request('POST', '/', ['booking' => [
            'firstName' => 'Jeanne', 'lastName' => 'Martin', 'email' => 'jeanne@example.com', 'phone' => '06 12 34 56 78', 'consent' => '1',
            'guests' => array_map(fn ($g) => ['firstName' => $g[0], 'lastName' => $g[1], 'age' => (string) $g[2], 'meal' => $g[3], 'comment' => $g[4] ?? ''], $guests),
            '_token' => $token,
        ]]);
    }
}
