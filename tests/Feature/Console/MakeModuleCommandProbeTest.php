<?php

namespace CoreFoundation\Tests\Feature\Console;

use App\Modules\Order\Models\Order;
use Illuminate\Support\Facades\Schema;
use CoreFoundation\Tests\PackageTestCase;
use Illuminate\Database\Schema\Blueprint;
use App\Modules\Order\Services\OrderService;
use App\Modules\Order\Repositories\OrderRepository;
use App\Modules\Order\Http\Controllers\OrderController;

class MakeModuleCommandProbeTest extends PackageTestCase
{
    public function test_core_make_generates_a_full_crud_module(): void
    {
        $this->artisan('core:make', ['name' => 'Order'])
            ->expectsChoice('What are you building?', 'crud', [
                'crud' => 'CRUD — model, controller, service, repository, resource, test',
                'custom' => 'Custom Feature — service, data object, test only',
            ])
            ->expectsQuestion('Components to generate', [
                'model', 'factory', 'repository', 'service', 'controller',
                'store-request', 'update-request', 'resource', 'collection',
                'data-object', 'policy', 'provider', 'test',
            ])
            ->expectsQuestion('Root namespace', 'App')
            ->expectsConfirmation('Use module folder structure?', 'yes')
            ->assertExitCode(0);

        $base = base_path();
        fwrite(STDERR, "base_path resolves to: {$base}\n");

        $expectedFiles = [
            'app/Modules/Order/Models/Order.php',
            'database/factories/OrderFactory.php',
            'app/Modules/Order/Repositories/OrderRepository.php',
            'app/Modules/Order/Services/OrderService.php',
            'app/Modules/Order/Http/Controllers/OrderController.php',
            'app/Modules/Order/Http/Requests/StoreOrderRequest.php',
            'app/Modules/Order/Http/Requests/UpdateOrderRequest.php',
            'app/Modules/Order/Resources/OrderResource.php',
            'app/Modules/Order/Resources/OrderCollection.php',
            'app/Modules/Order/DataObjects/OrderData.php',
            'app/Modules/Order/Policies/OrderPolicy.php',
            'app/Modules/Order/Providers/OrderServiceProvider.php',
            'tests/Feature/OrderTest.php',
        ];

        foreach ($expectedFiles as $relative) {
            $full = base_path($relative);
            $this->assertFileExists($full, "Missing generated file: {$relative}");

            $contents = file_get_contents($full);
            $this->assertStringNotContainsString('{{', $contents, "Unsubstituted placeholder left in: {$relative}");

            $lintResult = shell_exec('php -l '.escapeshellarg($full).' 2>&1');
            $this->assertStringContainsString('No syntax errors detected', (string) $lintResult, "Syntax error in {$relative}: {$lintResult}");
        }

        // Syntax-valid is not the same as loadable — a covariant/contravariant
        // signature mismatch against a CoreFoundation base class only fatals the
        // moment the class is actually declared (require'd), same class of bug
        // found this session in MaintenanceModeException/BasePolicy/BaseObserver.
        // Require every generated file in dependency order and confirm none of
        // them fatal at class-declaration time.
        require_once base_path('app/Modules/Order/Models/Order.php');
        require_once base_path('app/Modules/Order/Repositories/OrderRepository.php');
        require_once base_path('app/Modules/Order/Services/OrderService.php');
        require_once base_path('app/Modules/Order/Resources/OrderResource.php');
        require_once base_path('app/Modules/Order/Resources/OrderCollection.php');
        require_once base_path('app/Modules/Order/Http/Requests/StoreOrderRequest.php');
        require_once base_path('app/Modules/Order/Http/Requests/UpdateOrderRequest.php');
        require_once base_path('app/Modules/Order/Http/Controllers/OrderController.php');
        require_once base_path('app/Modules/Order/Policies/OrderPolicy.php');
        require_once base_path('app/Modules/Order/Providers/OrderServiceProvider.php');
        require_once base_path('app/Modules/Order/DataObjects/OrderData.php');

        // Every generated class must actually be resolvable through the
        // container — this is how Laravel really constructs controllers,
        // services, and repositories; a constructor-injection mismatch
        // (e.g. wrong property name/type) would surface here.
        $repository = $this->app->make(OrderRepository::class);
        $this->assertInstanceOf(OrderRepository::class, $repository);

        $service = $this->app->make(OrderService::class);
        $this->assertInstanceOf(OrderService::class, $service);

        $controller = $this->app->make(OrderController::class);
        $this->assertInstanceOf(OrderController::class, $controller);

        // core:make does not (and should not) guess a migration — that's the
        // documented "next step" a real developer takes. Create the table here
        // so the CRUD flow below exercises the generated code against a real
        // database, not just class-loading.
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });

        // $fillable is intentionally empty in the generated model (deny-by-default,
        // matching the rest of the package) — addFillable() is the documented,
        // modular way to extend it, exactly as a real developer would.
        Order::addFillable(['title']);

        // Exercise the real CRUD path end to end through the generated service —
        // not a mock, the actual generated code talking to a real database.
        $created = $service->create(['title' => 'Generated Order']);
        $this->assertNotNull($created->id);

        $fetched = $service->fetchById($created->id);
        $this->assertEquals('Generated Order', $fetched->title);

        $updated = $service->update($created->id, ['title' => 'Updated Order']);
        $this->assertEquals('Updated Order', $updated->title);

        $all = $service->fetchAll();
        $this->assertGreaterThanOrEqual(1, $all->count());

        $deleted = $service->delete($created->id);
        $this->assertTrue($deleted);
    }
}
