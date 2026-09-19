<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Tests\Support\WordFixture;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || ! str_contains(config('database.connections.sqlite.database'), 'browser-test')) {
    throw new RuntimeException('Browser fixtures require the isolated test database.');
}
User::firstOrCreate(['email' => 'browser@example.test'], ['name' => 'Browser Signer', 'password' => 'browser-test-password']);
$fixture = new class
{
    use WordFixture;
};
file_put_contents(dirname(config('database.connections.sqlite.database')).'/agreement.docx', $fixture->wordBytes());
file_put_contents(dirname(config('database.connections.sqlite.database')).'/layout.docx', $fixture->layoutWordBytes());
