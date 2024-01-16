<?php

namespace SCTeam\Auth\Tests\Feature\Controllers;

use Faker\Factory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\Fluent\AssertableJson;
use SCTeam\Auth\Enums\Gender;
use SCTeam\Auth\Enums\UserRole;
use SCTeam\Auth\Enums\UserStatus;
use SCTeam\Auth\Models\User;
use SCTeam\Auth\Tests\TestCase;
use SCTeam\Auth\Tests\CreateUsers;

class UsersApiControllerTest extends TestCase
{
    use DatabaseTransactions;
    use CreateUsers;

    public function testStore()
    {
        $admin = $this->makeAdmin();
        $factory = Factory::create();
        $email = $factory->email;
        $data = [
            'first_name' => $factory->firstName,
            'last_name' => $factory->lastName,
            'phone' => $factory->phoneNumber,
            'email' => $email,
            'gender' => $factory->randomElement(Gender::getValues()),
            'user_roles' => [$factory->randomElement(UserRole::getValues())],
            'status' => $factory->randomElement(UserStatus::getValues()),
            'meta' => [
                'some_meta' => 'meta value',
            ],
            'rendered_dynamic_tabs' => ["base"],
            'submit' => 'save'
        ];
        $response = $this
            ->withHeaders([
                'Authorization' => 'Bearer ' . $admin->api_token,
            ])
            ->post(route('api.users.store'), $data);
        
        $response->assertJson(fn(AssertableJson $json) => $json
            ->where('message', __t('scteam.auth::common.user_added', 'User added!'))
            ->where('success', true)
            ->has('redirect')
        );
        $this->assertEquals('meta value', User::whereEmail($email)->first()->getMetaAttr('some_meta'));
    }

    public function testUpdate()
    {
        $admin = $this->makeAdmin();
        $factory = Factory::create();
        $user = User::factory()->create();
        $newName = $factory->firstName;
        $data = [
            'first_name' => $newName,
            'last_name' => $factory->lastName,
            'email' => $user->email,
            'gender' => $user->gender->value,
            'phone' => $factory->phoneNumber,
            'user_roles' => [UserRole::Admin],
            'status' => $user->status->value,
            'meta' => [
                'some_meta' => 'meta value',
            ],
            'rendered_dynamic_tabs' => ["base"],
            'submit' => 'save'
        ];
        $response = $this
            ->withHeaders([
                'Authorization' => 'Bearer ' . $admin->api_token,
            ])
            ->post(route('api.users.update', $user), $data);

        $response->assertJson(fn(AssertableJson $json) => $json
            ->where('message', __t('scteam.auth::common.user_updated', 'User updated!'))
            ->where('success', true)
            ->missing('redirect')
        );
        $this->assertEquals($newName, $user->fresh()->first_name);
    }

    public function testIndex()
    {
        $admin = $this->makeAdmin();
        $users = User::factory(10)->create();
        for ($i = 1; $i <= 3; $i++) {
            $randomUser = $users->random();
            $params = ['search' => $randomUser->first_name];

            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $admin->api_token,
            ])->get(route('api.users.index', $params));

            $data = array_map(fn($user) => $user['label'], $response->json()['data']);
            $response->assertStatus(200)->assertJson([
                'success' => true,
                'data' => true,
            ]);
            $this->assertContains($randomUser->name, $data);
        }
    }

    public function testIndexWithExceptId()
    {
        $admin = $this->makeAdmin();
        $users = User::factory(10)->create();

        for ($i = 1; $i <= 3; $i++) {
            $randomUser = $users->random();
            $randomUserKey = $randomUser->getKey();
            $params = ['except_ids' => [$randomUserKey]];
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $admin->api_token,
            ])->get(route('api.users.index', $params));

            $response->assertStatus(200)->assertJson([
                'success' => true,
                'data' => true,
            ]);
            $data = array_map(fn($user) => $user['label'], $response->json()['data']);
            $this->assertNotContains($randomUser->name, $data);
            $this->assertContains($users->whereNotIn('id', $randomUserKey)->random()->name, array_values($data));
        }
    }

    public function testIndexWithManyExceptIds()
    {
        $admin = $this->makeAdmin();
        $users = User::factory(10)->create();

        for ($i = 1; $i <= 3; $i++) {
            $randomUser1 = $users->random();
            $randomUserKey1 = $randomUser1->getKey();

            $randomUser2 = $users->random()->first();
            $randomUserKey2 = $randomUser2->getKey();

            $params = ['except_ids' => [$randomUserKey1, $randomUserKey2]];

            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $admin->api_token,
            ])->get(route('api.users.index', $params));

            $data = array_map(fn($user) => $user['label'], $response->json()['data']);
            $response->assertStatus(200)->assertJson([
                'success' => true,
                'data' => true,
            ]);
            $this->assertNotContains($randomUser1->name, $data);
            $this->assertNotContains($randomUser2->name, $data);
            $this->assertContains(
                $users->whereNotIn('id', $params['except_ids'])->random()->name,
                $data
            );
        }
    }
}
