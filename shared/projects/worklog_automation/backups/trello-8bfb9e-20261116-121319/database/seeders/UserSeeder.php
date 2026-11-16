<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Add or edit users here. Only the keys you list (besides email) are updated.
        $emails = ['waseemchanna75@gmail.com', 'bhomika.kumari@avaib.com', 'munawar.khan@avaib.com', 'waqas.softweb@gmail.com', 'rafia.rashid@avaib.com'];
        $users = User::whereIn('email', $emails)->get();

        foreach ($users as $data) {
            $email = strtolower(trim($data['email']));
            $values = collect($data)
                ->only(['name', 'designation'])   // fields allowed to change
                ->all();

            $user = User::where('email', $email)->first();

            if ($user) {
                // Update only the given fields
                if ($values) {
                    $user->update($values);
                }

                // Backfill the UUID for old rows that don't have one
                if (empty($user->uuid)) {
                    $user->update(['uuid' => (string) Str::uuid()]);
                }

                $this->command?->info("Updated: {$email}");
            } else {
                User::create($values + [
                    'email' => $email,
                    'uuid'  => (string) Str::uuid(),
                ]);

                $this->command?->info("Created: {$email}");
            }
        }
    }
}
