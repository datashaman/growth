<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
| Browser tests drive real pages through Playwright. They run the app in a
| separate server process, so an in-memory SQLite database is not shared with
| the test process — RefreshDatabase is deliberately omitted here. Tests that
| need seeded data must use a file-based or external database.
*/
pest()->extend(TestCase::class)
    ->in('Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function readResource(string $serverClass, string $uri): TestResponse
{
    $shim = new class($uri) extends Laravel\Mcp\Server\Resource
    {
        public function __construct(private string $concreteUri) {}

        public function toMethodCall(): array
        {
            return ['uri' => $this->concreteUri];
        }

        public function handle(Request $request): Response
        {
            return Response::text('');
        }
    };

    return $serverClass::resource($shim);
}
