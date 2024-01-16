<?php

namespace SCTeam\Auth\Tests;

use SCTeam\Auth\SCTeamServiceProvider;

class TestCase extends \SCTeam\Base\Tests\TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            SCTeamServiceProvider::class,
        ];
    }
}