<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class TopicsTableSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        Artisan::call('topics:get');
    }
}
