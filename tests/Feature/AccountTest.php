<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AccountStarter;
use Database\Seeders\LedgerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_sign_in(): void
    {
        $this->seed(LedgerSeeder::class);

        $this->post('/login', [
            'email' => User::OWNER_EMAIL,
            'password' => User::OWNER_PASSWORD,
        ])->assertRedirect('/');

        $this->assertAuthenticated();
        $this->assertTrue(User::owner()->isSuperAdmin());
    }

    public function test_a_turned_off_account_cannot_sign_in(): void
    {
        $this->seed(LedgerSeeder::class);
        User::query()->where('email', User::OWNER_EMAIL)->update(['is_active' => false]);

        $this->post('/login', [
            'email' => User::OWNER_EMAIL,
            'password' => User::OWNER_PASSWORD,
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admin_can_create_a_separate_account(): void
    {
        $this->seed(LedgerSeeder::class);
        $admin = User::owner();
        $this->assertTrue($admin->isSuperAdmin());

        $this->actingAs($admin)
            ->post('/people', [
                'name' => 'Ate',
                'email' => 'ate@localhost',
                'password' => 'password1',
                'password_confirmation' => 'password1',
                'role' => 'member',
                'living_plan' => 12000,
            ])
            ->assertRedirect();

        $ate = User::query()->where('email', 'ate@localhost')->firstOrFail();
        $this->assertSame('member', $ate->role);
        $this->assertSame('12000.00', $ate->living_plan);
        $this->assertTrue(
            Category::query()->withoutGlobalScopes()->where('user_id', $ate->id)->where('slug', 'daily')->exists()
        );

        $this->actingAs($ate)
            ->get('/?year=2026&month=10')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('figures.income', null)
                ->where('debts', fn ($debts) => $debts->count() === 0)
                ->where('figures.variable_plan', 12000));

        $this->actingAs($ate)->get('/people')->assertForbidden();
        $this->actingAs($ate)->get('/vehicles')->assertOk();
    }

    public function test_a_member_does_not_see_another_house(): void
    {
        $this->seed(LedgerSeeder::class);
        $sister = User::factory()->create(['email' => 'sister@localhost', 'living_plan' => 10000]);
        app(AccountStarter::class)->provision($sister);

        $kabanBike = Vehicle::query()->withoutGlobalScopes()->where('name', 'Z400')->firstOrFail();

        $this->actingAs($sister)
            ->get('/vehicles')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('vehicles', fn ($rows) => $rows->count() === 0));

        $this->actingAs($sister)
            ->put("/vehicles/{$kabanBike->id}", ['current_odometer' => 999])
            ->assertNotFound();
    }

    public function test_a_member_can_add_their_own_vehicle(): void
    {
        $this->seed(LedgerSeeder::class);
        $sister = User::factory()->create();
        app(AccountStarter::class)->provision($sister);

        $this->actingAs($sister)
            ->post('/vehicles', [
                'name' => 'Mio',
                'make' => 'Yamaha',
                'current_odometer' => 1200,
                'seed_bike_parts' => true,
            ])
            ->assertRedirect();

        $this->assertTrue(Vehicle::query()->where('name', 'Mio')->where('user_id', $sister->id)->exists());
        $bike = Vehicle::query()->where('name', 'Mio')->firstOrFail();
        $this->assertSame(11, $bike->parts()->count());
    }

    public function test_superadmin_cannot_be_deleted(): void
    {
        $this->seed(LedgerSeeder::class);
        $admin = User::owner();

        $this->actingAs($admin)
            ->delete('/people/'.$admin->id)
            ->assertForbidden();

        $this->assertFalse($admin->delete());
        $this->assertFalse($admin->forceDelete());
        $this->assertNotSoftDeleted($admin);
        $this->assertTrue(User::query()->whereKey($admin->id)->exists());
    }

    public function test_removing_a_member_soft_deletes_their_house(): void
    {
        $this->seed(LedgerSeeder::class);
        $admin = User::owner();
        $sister = User::factory()->create(['email' => 'sister@localhost', 'role' => User::ROLE_MEMBER]);
        app(AccountStarter::class)->provision($sister);

        $this->actingAs($sister)
            ->post('/vehicles', [
                'name' => 'Mio',
                'make' => 'Yamaha',
                'current_odometer' => 200,
            ])
            ->assertRedirect();

        $bike = Vehicle::query()->where('name', 'Mio')->firstOrFail();

        $this->actingAs($admin)
            ->delete('/people/'.$sister->id)
            ->assertRedirect();

        $this->assertSoftDeleted($sister);
        $this->assertSoftDeleted($bike);
        $this->assertNull(User::query()->where('email', 'sister@localhost')->first());
    }

    public function test_removing_a_log_item_soft_deletes_it(): void
    {
        $this->seed(LedgerSeeder::class);
        $admin = User::owner();
        $row = Transaction::query()->where('type', 'daily')->firstOrFail();

        $this->actingAs($admin)
            ->delete('/transactions/'.$row->id)
            ->assertRedirect();

        $this->assertSoftDeleted($row);
        $this->assertDatabaseHas('transactions', ['id' => $row->id]);
    }
}
