<?php

namespace App\Service;

use Stripe\StripeClient;
use Stripe\Checkout\Session;

class StripeService
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient($_ENV['STRIPE_SECRET_KEY']);
    }

    /**
     * Create a Stripe Checkout Session for Event Registration
     */
    public function createRegistrationSession(float $price, string $eventName, int $eventId): Session
    {
        // Stripe requires amounts in cents (e.g., $25.00 becomes 2500)
        $amountInCents = (int)($price * 100);

        return $this->stripe->checkout->sessions->create([
            'payment_method_types' => ['card'], // Stripe handles Google/Apple pay automatically if enabled in dashboard
            'line_items' => [[
                'price_data' => [
                    'currency' => 'myr',
                    'product_data' => [
                        'name' => "Registration for: " . $eventName,
                    ],
                    'unit_amount' => $amountInCents,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            // Send them to a success page with the checkout session ID
            'success_url' => 'https://yourfrontend.com/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => 'https://yourfrontend.com/event-cancelled',
            // Pass metadata so you know *who* and *what* was paid for when processing webhooks
            'metadata' => [
                'event_id' => $eventId,
                //'user_id' => $userId,
            ]
        ]);
    }
}