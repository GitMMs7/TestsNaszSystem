<?php

namespace SCTeam\Auth\Tests;

use SCTeam\Auth\Enums\UserRole;
use SCTeam\Auth\Enums\UserStatus;

trait CreateUsers
{
    public function makeAdmin(array $attributes = [])
    {
        $admin = \SCTeamAuth::userModelClass()::factory()->create(array_merge(
            ['status' => UserStatus::Active()],
            $attributes,
        ));
        $admin->assignRole(UserRole::Admin());
        return $admin;
    }
    public function makeUser(array $attributes = [])
    {
        $user = \SCTeamAuth::userModelClass()::factory()->create(array_merge(
            ['status' => UserStatus::Active()],
            $attributes,
        ));
        $user->assignRole(UserRole::User());
        return $user;
    }
}
