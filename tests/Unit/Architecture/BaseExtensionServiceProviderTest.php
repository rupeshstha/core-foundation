<?php

namespace CoreFoundation\Tests\Unit\Architecture;

use stdClass;
use LogicException;
use ReflectionClass;
use Illuminate\Http\Request;
use CoreFoundation\Entities\BaseModel;
use CoreFoundation\Services\BaseService;
use CoreFoundation\Tests\PackageTestCase;
use Illuminate\Database\Eloquent\Builder;
use CoreFoundation\Transformers\BaseResource;
use CoreFoundation\Traits\Models\ModelFillables;
use CoreFoundation\Traits\Models\ModelSearchable;
use CoreFoundation\Repositories\Filter\FilterApplicator;
use CoreFoundation\Providers\BaseExtensionServiceProvider;
use CoreFoundation\Repositories\Filter\Contracts\FilterOperator;

// ---------------------------------------------------------------------------
// Stub types
// ---------------------------------------------------------------------------

class ExtTestModel extends BaseModel
{
    protected $table = 'test_posts'; // reuse existing migration

    protected $fillable = ['title'];
}

class ExtTestResource extends BaseResource
{
    protected function fields(Request $request): array
    {
        return ['id' => $this->resource->id];
    }

    public static function resetRegistry(): void
    {
        static::$additionalFields[static::class] = [];
        static::$removedFields[static::class] = [];
    }
}

class ExtTestService extends BaseService {}

class ExtTestOperator implements FilterOperator
{
    public function identifier(): string
    {
        return '__like_ext_';
    }

    public function apply(Builder $builder, string $column, mixed $value): void
    {
        $builder->where($column, 'LIKE', "%{$value}%");
    }
}

// ---------------------------------------------------------------------------
// Concrete provider stubs — each tests one hook
// ---------------------------------------------------------------------------

class ModelExtensionProvider extends BaseExtensionServiceProvider
{
    protected function extendModels(): void
    {
        $this->model(ExtTestModel::class)
            ->fillable(['extra_field', 'another_field'])
            ->searchable(['extra_field']);
    }
}

class ResourceExtensionProvider extends BaseExtensionServiceProvider
{
    protected function extendResources(): void
    {
        $this->resource(ExtTestResource::class)
            ->field('extra', fn ($item) => 'injected')
            ->remove('id');
    }
}

class ServiceExtensionProvider extends BaseExtensionServiceProvider
{
    protected function extendServices(): void
    {
        $this->service(ExtTestService::class)
            ->pipe('action', stdClass::class);
    }
}

class OperatorExtensionProvider extends BaseExtensionServiceProvider
{
    protected function extendOperators(): void
    {
        $this->operator(new ExtTestOperator);
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class BaseExtensionServiceProviderTest extends PackageTestCase
{
    protected function tearDown(): void
    {
        // Reset model static state via reflection (no public clear API)
        $fillableRef = new ReflectionClass(ModelFillables::class);
        $prop = $fillableRef->getProperty('additionalFillable');
        $prop->setAccessible(true);
        $current = $prop->getValue(null);
        unset($current[ExtTestModel::class]);
        $prop->setValue(null, $current);

        $searchableRef = new ReflectionClass(ModelSearchable::class);
        $sProp = $searchableRef->getProperty('additionalSearchable');
        $sProp->setAccessible(true);
        $current = $sProp->getValue(null);
        unset($current[ExtTestModel::class]);
        $sProp->setValue(null, $current);

        ExtTestResource::resetRegistry();
        ExtTestService::clearAllPipes();
        FilterApplicator::reset();

        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Guard — model() / resource() / service() type-check
    // -----------------------------------------------------------------------

    public function test_model_factory_throws_when_class_does_not_extend_base_model(): void
    {
        $provider = new class($this->app) extends BaseExtensionServiceProvider
        {
            public function callModel(string $class): void
            {
                $this->model($class);
            }
        };

        $this->expectException(LogicException::class);
        $provider->callModel(stdClass::class);
    }

    public function test_resource_factory_throws_when_class_does_not_extend_base_resource(): void
    {
        $provider = new class($this->app) extends BaseExtensionServiceProvider
        {
            public function callResource(string $class): void
            {
                $this->resource($class);
            }
        };

        $this->expectException(LogicException::class);
        $provider->callResource(stdClass::class);
    }

    public function test_service_factory_throws_when_class_does_not_extend_base_service(): void
    {
        $provider = new class($this->app) extends BaseExtensionServiceProvider
        {
            public function callService(string $class): void
            {
                $this->service($class);
            }
        };

        $this->expectException(LogicException::class);
        $provider->callService(stdClass::class);
    }

    // -----------------------------------------------------------------------
    // extendModels() — fillable and searchable registration
    // -----------------------------------------------------------------------

    public function test_extend_models_registers_additional_fillable_fields(): void
    {
        $provider = new ModelExtensionProvider($this->app);
        $provider->register();
        $provider->boot();

        $fillable = (new ExtTestModel)->getFillable();

        $this->assertContains('extra_field', $fillable);
        $this->assertContains('another_field', $fillable);
    }

    public function test_extend_models_registers_searchable_columns(): void
    {
        $provider = new ModelExtensionProvider($this->app);
        $provider->register();
        $provider->boot();

        $searchable = ExtTestModel::getSearchable();

        $this->assertContains('extra_field', $searchable);
    }

    // -----------------------------------------------------------------------
    // extendResources() — field addition and removal
    // -----------------------------------------------------------------------

    public function test_extend_resources_adds_field_to_resource(): void
    {
        $provider = new ResourceExtensionProvider($this->app);
        $provider->register();
        $provider->boot();

        $resource = new ExtTestResource((object) ['id' => 1]);
        $result = $resource->toArray(new Request);

        $this->assertArrayHasKey('extra', $result);
        $this->assertSame('injected', $result['extra']);
    }

    public function test_extend_resources_removes_field_from_resource(): void
    {
        $provider = new ResourceExtensionProvider($this->app);
        $provider->register();
        $provider->boot();

        $resource = new ExtTestResource((object) ['id' => 1]);
        $result = $resource->toArray(new Request);

        $this->assertArrayNotHasKey('id', $result);
    }

    // -----------------------------------------------------------------------
    // extendServices() — pipe registration
    // -----------------------------------------------------------------------

    public function test_extend_services_registers_pipe_on_service(): void
    {
        $provider = new ServiceExtensionProvider($this->app);
        $provider->register();
        $provider->boot();

        $pipes = (new ExtTestService)->getPipes('action');

        $this->assertContains(stdClass::class, $pipes);
    }

    // -----------------------------------------------------------------------
    // extendOperators() — custom filter operator registration
    // -----------------------------------------------------------------------

    public function test_extend_operators_registers_operator_with_filter_applicator(): void
    {
        $provider = new OperatorExtensionProvider($this->app);
        $provider->register();
        $provider->boot();

        $operators = FilterApplicator::operators();

        $this->assertArrayHasKey('__like_ext_', $operators);
        $this->assertInstanceOf(ExtTestOperator::class, $operators['__like_ext_']);
    }

    // -----------------------------------------------------------------------
    // registerBindings() — lifecycle order (register runs before boot)
    // -----------------------------------------------------------------------

    public function test_register_bindings_runs_during_register_phase(): void
    {
        $provider = new class($this->app) extends BaseExtensionServiceProvider
        {
            public bool $wasBound = false;

            protected function registerBindings(): void
            {
                $this->wasBound = true;
            }
        };

        $provider->register();

        $this->assertTrue($provider->wasBound);
    }

    // -----------------------------------------------------------------------
    // No-op hooks — all hooks default to empty and must not throw
    // -----------------------------------------------------------------------

    public function test_no_op_hooks_do_not_throw(): void
    {
        $this->expectNotToPerformAssertions();

        $provider = new class($this->app) extends BaseExtensionServiceProvider {};
        $provider->register();
        $provider->boot();
    }
}
