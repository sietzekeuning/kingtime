<?php

declare(strict_types=1);

use App\Domain\User\Models\User;

it('shows the sign-up page while no user exists', function (): void {
    $this->get(route('register'))->assertOk();
    $this->get(route('login'))->assertInertia(fn ($page) => $page->where('canRegister', true));
});

it('closes sign-up once the first user exists', function (): void {
    User::factory()->create();

    $this->get(route('register'))->assertForbidden();
    $this->get(route('login'))->assertInertia(fn ($page) => $page->where('canRegister', false));

    $this->post(route('register'), [
        'name' => 'Second',
        'email' => 'second@example.com',
        'password' => 'Password!123',
        'password_confirmation' => 'Password!123',
    ])->assertForbidden();

    expect(User::query()->count())->toBe(1);
});

it('keeps sign-up open when the installation allows it', function (): void {
    config()->set('kingtime.allow_registration', true);
    User::factory()->create();

    $this->get(route('register'))->assertOk();
});
