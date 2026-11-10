<?php

namespace App\Payment;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HelloAsso Checkout API (free for associations).
 *
 * @see https://dev.helloasso.com/docs/int%C3%A9grer-le-paiement-sur-votre-site
 */
final class HelloAssoPaymentProvider implements PaymentProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly CacheInterface $cache,
        private readonly UrlGeneratorInterface $urls,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(HELLOASSO_API_URL)%')] private readonly string $apiUrl,
        #[Autowire('%env(HELLOASSO_CLIENT_ID)%')] private readonly string $clientId,
        #[Autowire('%env(HELLOASSO_CLIENT_SECRET)%')] private readonly string $clientSecret,
        #[Autowire('%env(HELLOASSO_ORGANIZATION_SLUG)%')] private readonly string $organization,
    ) {
    }

    public static function getName(): string
    {
        return 'helloasso';
    }

    public function start(Payable $order): string
    {
        $abs = UrlGeneratorInterface::ABSOLUTE_URL;
        $response = $this->http->request('POST', $this->endpoint('/checkout-intents'), [
            'auth_bearer' => $this->token(),
            'json' => [
                'totalAmount' => $order->getAmountDue(),
                'initialAmount' => $order->getAmountDue(),
                'itemName' => mb_substr($order->getPaymentLabel(), 0, 250),
                'backUrl' => $this->urls->generate($order->getPaymentBackRoute(), [], $abs),
                'errorUrl' => $this->urls->generate('payment_return', ['token' => $order->getAccessToken()], $abs),
                'returnUrl' => $this->urls->generate('payment_return', ['token' => $order->getAccessToken()], $abs),
                'containsDonation' => false,
                'payer' => [
                    'firstName' => $order->getFirstName(),
                    'lastName' => $order->getLastName(),
                    'email' => $order->getEmail(),
                ],
                'metadata' => ['reference' => $order->getReference(), 'source' => 'soiree'],
            ],
        ]);
        // 400 = HelloAsso refuses the order data (usually the buyer's name): its message is meant for the buyer
        if (400 === $response->getStatusCode()) {
            $error = $response->toArray(false)['errors'][0]['message'] ?? null;
            $this->logger->warning('HelloAsso : commande refusée', ['order' => $order->getReference(), 'error' => $error]);
            throw new PaymentRefusedException($error ?? 'Les informations saisies ont été refusées par HelloAsso.');
        }
        // Other refusals concern the club's HelloAsso account: keep HelloAsso's explanation for the admin e-mail
        if ($response->getStatusCode() >= 300) {
            $this->logger->error('HelloAsso : création du paiement refusée', [
                'order' => $order->getReference(),
                'status' => $response->getStatusCode(),
                'response' => mb_substr($response->getContent(false), 0, 1000),
            ]);
        }
        $data = $response->toArray();
        $order->addPaymentIntent((string) $data['id']);

        return $data['redirectUrl'];
    }

    /** Checks every payment page opened for the order, most recent first, and keeps the paid one as reference. */
    public function isPaid(Payable $order): bool
    {
        foreach (array_reverse($order->getPaymentIntents()) as $intent) {
            if ($this->isIntentPaid($order, $intent)) {
                $order->setPaymentReference($intent);

                return true;
            }
        }

        return false;
    }

    private function isIntentPaid(Payable $order, string $intent): bool
    {
        $data = $this->http->request('GET', $this->endpoint('/checkout-intents/'.$intent), [
            'auth_bearer' => $this->token(),
        ])->toArray();

        // The intent only holds an "réservation" once the payment went through; check the amount as well.
        $payments = $data['order']['payments'] ?? [];
        $paid = array_sum(array_map(
            fn (array $p) => in_array($p['state'] ?? '', ['Authorized', 'Registered'], true) ? (int) $p['amount'] : 0,
            $payments,
        ));
        if ($paid > 0 && $paid < $order->getAmountDue()) {
            $this->logger->error('HelloAsso: montant payé inférieur au total', ['order' => $order->getReference(), 'intent' => $intent, 'paid' => $paid]);
        }

        return $paid >= $order->getAmountDue();
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->apiUrl, '/').'/v5/organizations/'.$this->organization.$path;
    }

    private function token(): string
    {
        return $this->cache->get('helloasso_token_'.md5($this->clientId), function (ItemInterface $item): string {
            $data = $this->http->request('POST', rtrim($this->apiUrl, '/').'/oauth2/token', [
                'body' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
            ])->toArray();
            $item->expiresAfter(max(60, (int) ($data['expires_in'] ?? 1800) - 60));

            return $data['access_token'];
        });
    }
}
