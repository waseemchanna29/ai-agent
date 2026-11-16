<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_user_listing(): void
    {
        $this->get('/users')->assertRedirect('/login');
    }

    public function test_logged_in_user_can_view_the_user_listing(): void
    {
        $user = User::factory()->create(['name' => 'Alice']);
        User::factory()->create(['name' => 'Bob']);

        $this->actingAs($user)
            ->get('/users')
            ->assertOk()
            ->assertSeeText('Alice')
            ->assertSeeText('Bob');
    }

    public function test_user_can_be_created_with_hashed_password(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor)->post('/users', [
            'name' => 'New Person',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'status' => 'active',
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);

        $created = User::where('email', 'new@example.com')->first();
        $this->assertNotSame('password123', $created->password);
        $this->assertTrue(Hash::check('password123', $created->password));
    }

    public function test_create_rejects_duplicate_email(): void
    {
        $actor = User::factory()->create();
        User::factory()->create(['email' => 'dupe@example.com']);

        $this->actingAs($actor)->post('/users', [
            'name' => 'Dupe',
            'email' => 'dupe@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'status' => 'active',
        ])->assertSessionHasErrors('email');
    }

    public function test_create_requires_matching_password(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor)->post('/users', [
            'name' => 'Mismatch',
            'email' => 'mismatch@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
            'status' => 'active',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'mismatch@example.com']);
    }

    public function test_user_can_be_updated_and_blank_password_is_kept(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'password' => Hash::make('original-pass'),
        ]);

        $this->actingAs($actor)->put(route('users.update', $target), [
            'name' => 'New Name',
            'email' => 'updated@example.com',
            'password' => '',
            'password_confirmation' => '',
            'status' => 'active',
        ])->assertRedirect(route('users.index'));

        $target->refresh();
        $this->assertSame('New Name', $target->name);
        $this->assertSame('updated@example.com', $target->email);
        // Blank password → original password unchanged.
        $this->assertTrue(Hash::check('original-pass', $target->password));
    }

    public function test_update_can_change_the_password(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create([
            'password' => Hash::make('original-pass'),
        ]);

        $this->actingAs($actor)->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
            'status' => 'active',
        ])->assertRedirect(route('users.index'));

        $target->refresh();
        $this->assertTrue(Hash::check('brand-new-pass', $target->password));
    }

    public function test_update_allows_keeping_the_same_email(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create(['email' => 'same@example.com']);

        $this->actingAs($actor)->put(route('users.update', $target), [
            'name' => 'Renamed',
            'email' => 'same@example.com',
            'password' => '',
            'password_confirmation' => '',
            'status' => 'active',
        ])->assertRedirect(route('users.index'));

        $target->refresh();
        $this->assertSame('Renamed', $target->name);
    }

    public function test_user_can_delete_another_user(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->delete(route('users.destroy', $target))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_user_cannot_delete_their_own_account(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor)
            ->delete(route('users.destroy', $actor))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $actor->id]);
    }

    public function test_user_can_be_created_as_inactive(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor)->post('/users', [
            'name' => 'Blocked Person',
            'email' => 'blocked@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'status' => 'inactive',
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'blocked@example.com',
            'status' => 'inactive',
        ]);
    }

    public function test_create_rejects_an_invalid_status(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor)->post('/users', [
            'name' => 'Bad Status',
            'email' => 'badstatus@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'status' => 'banned',
        ])->assertSessionHasErrors('status');

        $this->assertDatabaseMissing('users', ['email' => 'badstatus@example.com']);
    }

    public function test_update_can_deactivate_a_user(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create(['status' => 'active']);

        $this->actingAs($actor)->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'password' => '',
            'password_confirmation' => '',
            'status' => 'inactive',
        ])->assertRedirect(route('users.index'));

        $target->refresh();
        $this->assertSame('inactive', $target->status);
    }
}
