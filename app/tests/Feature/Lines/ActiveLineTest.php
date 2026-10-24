<?php

use App\Enums\BusinessLine;
use App\Enums\Role;
use App\Models\User;
use App\Support\ActiveLine;

it('defaults to Naraya and switches per portal session', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    expect(ActiveLine::current())->toBe(BusinessLine::Naraya);

    $this->actingAs($admin)->from('http://'.config('app.admin_domain').'/products')
        ->post('http://'.config('app.admin_domain').'/lini', ['line' => 'toys'])
        ->assertRedirect('http://'.config('app.admin_domain').'/products');

    expect(session('line'))->toBe('toys')->and(ActiveLine::current())->toBe(BusinessLine::Toys);
});

it('lets sales switch lines on the sales portal too, and rejects unknown lines', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);

    $this->actingAs($sales)->post(salesUrl('/lini'), ['line' => 'toys'])->assertRedirect();
    expect(ActiveLine::current())->toBe(BusinessLine::Toys);

    $this->actingAs($sales)->post(salesUrl('/lini'), ['line' => 'mainan'])->assertSessionHasErrors('line');
    expect(ActiveLine::current())->toBe(BusinessLine::Toys);
});

it('shows the line switch with the active line pressed in both portal layouts', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $sales = User::factory()->create(['role' => Role::Sales]);
    ActiveLine::set(BusinessLine::Toys);

    $this->actingAs($admin)->get('http://'.config('app.admin_domain').'/')
        ->assertSeeHtml('value="toys" aria-pressed="true"')
        ->assertSeeHtml('value="naraya" aria-pressed="false"')
        ->assertSee('Admin · Naraya Toys');

    $this->actingAs($sales)->get(salesUrl('/'))
        ->assertSeeHtml('value="toys" aria-pressed="true"');
});
