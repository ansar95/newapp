<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleUserSeeder extends Seeder
{
    /**
     * Seed dummy users for each application role (testing only).
     */
    public function run(): void
    {
        $users = [
            [
                'email' => 'superadmin@example.com',
                'name' => 'Super Admin',
                'role' => UserRole::Superadmin,
            ],
            [
                'email' => 'project.admin@example.com',
                'name' => 'Project Admin',
                'role' => UserRole::ProjectAdmin,
            ],
            [
                'email' => 'boq.cost@example.com',
                'name' => 'BOQ Cost Control',
                'role' => UserRole::BoqCostControl,
            ],
            [
                'email' => 'approver@example.com',
                'name' => 'Approver',
                'role' => UserRole::Approver,
            ],
            [
                'email' => 'finance@example.com',
                'name' => 'Finance',
                'role' => UserRole::Finance,
            ],
            [
                'email' => 'project.site@example.com',
                'name' => 'Project Site',
                'role' => UserRole::ProjectSite,
            ],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make('password'),
                    'role' => $user['role'],
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
