<?php

namespace App\Payment;

/** Quelque chose qui se paie en ligne : une réservation. */
interface Payable
{
    public function getReference(): ?string;

    /** Secret of the return and simulation URLs. */
    public function getAccessToken(): string;

    /** Amount in cents to pay online. */
    public function getAmountDue(): int;

    /** Shown on the HelloAsso payment page. */
    public function getPaymentLabel(): string;

    /** Route of the page the payer goes back to when leaving the payment page. */
    public function getPaymentBackRoute(): string;

    public function getFirstName(): string;

    public function getLastName(): string;

    public function getEmail(): string;

    public function isPaid(): bool;

    public function markPaid(string $provider, ?string $reference, ?\DateTimeImmutable $at = null): static;

    public function getPaymentProvider(): ?string;

    public function setPaymentProvider(?string $paymentProvider): static;

    public function getPaymentReference(): ?string;

    public function setPaymentReference(?string $paymentReference): static;

    /** @return list<string> */
    public function getPaymentIntents(): array;

    public function addPaymentIntent(string $intent): static;
}
