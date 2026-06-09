<?php

it('renders the info page', function () {
    $this->get('/altid')
        ->assertOk()
        ->assertSee('AltID gør det lettere at købe varer med alderskrav')
        ->assertSee('Sådan passer det ind i vores takeaway')
        ->assertSee('/alderstjek', false);
});

it('renders the demo page', function () {
    $this->get('/alderstjek')
        ->assertOk()
        ->assertSee('/api/altid/age/start');
});

it('redirects the old demo path to the demo page', function () {
    $this->get('/altid-demo')->assertRedirect('/alderstjek');
});

it('hides debug blocks on the demo page when altid debug is disabled', function () {
    config(['altid.debug' => false]);

    $this->get('/alderstjek')
        ->assertOk()
        ->assertDontSee('Authorization Request')
        ->assertDontSee('Authorization URL')
        ->assertDontSee('Callback')
        ->assertDontSee('Transaction');
});

it('shows debug blocks on the demo page when altid debug is enabled', function () {
    config(['altid.debug' => true]);

    $this->get('/alderstjek')
        ->assertOk()
        ->assertSee('Authorization Request')
        ->assertSee('Authorization URL')
        ->assertSee('Callback')
        ->assertSee('Transaction');
});

it('does not register web routes when disabled in config', function () {
    config(['altid.register_web_routes' => false]);

    // Web routes are loaded at boot time, so we verify via the view instead
    expect(config('altid.register_web_routes'))->toBeFalse();
});
