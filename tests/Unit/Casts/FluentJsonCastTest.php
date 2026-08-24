<?php

namespace CoreFoundation\Tests\Unit\Casts;

use JsonException;
use Illuminate\Support\Fluent;
use CoreFoundation\Casts\FluentJsonCast;
use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Tests\Stubs\Models\TestPost;

class FluentJsonCastTest extends PackageTestCase
{
    private FluentJsonCast $cast;

    private TestPost $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cast = new FluentJsonCast;
        // get()/set() never touch $model — a throwaway instance is enough.
        $this->model = new TestPost;
    }

    public function test_get_returns_null_for_null_value(): void
    {
        $this->assertNull($this->cast->get($this->model, 'settings', null, []));
    }

    public function test_get_wraps_a_flat_object_as_fluent_with_property_access(): void
    {
        $result = $this->cast->get($this->model, 'settings', '{"color":"dark","size":14}', []);

        $this->assertInstanceOf(Fluent::class, $result);
        $this->assertEquals('dark', $result->color);
        $this->assertEquals(14, $result->size);
    }

    public function test_get_recursively_wraps_nested_objects(): void
    {
        $json = '{"theme":{"color":"dark","font":{"size":14}}}';

        $result = $this->cast->get($this->model, 'settings', $json, []);

        $this->assertInstanceOf(Fluent::class, $result->theme);
        $this->assertInstanceOf(Fluent::class, $result->theme->font);
        $this->assertEquals('dark', $result->theme->color);
        $this->assertEquals(14, $result->theme->font->size);
    }

    public function test_get_keeps_json_lists_as_plain_arrays_but_wraps_their_object_elements(): void
    {
        $json = '{"items":[{"sku":"A1"},{"sku":"A2"}],"tags":["red","blue"]}';

        $result = $this->cast->get($this->model, 'settings', $json, []);

        $this->assertIsArray($result->items);
        $this->assertInstanceOf(Fluent::class, $result->items[0]);
        $this->assertEquals('A1', $result->items[0]->sku);
        $this->assertEquals('A2', $result->items[1]->sku);

        // Scalars in a list stay untouched.
        $this->assertSame(['red', 'blue'], $result->tags);
    }

    public function test_get_throws_on_malformed_json(): void
    {
        $this->expectException(JsonException::class);

        $this->cast->get($this->model, 'settings', '{not valid json', []);
    }

    public function test_set_returns_null_for_null_value(): void
    {
        $this->assertNull($this->cast->set($this->model, 'settings', null, []));
    }

    public function test_set_serializes_a_plain_array_to_json(): void
    {
        $json = $this->cast->set($this->model, 'settings', ['theme' => ['color' => 'dark']], []);

        $this->assertJsonStringEqualsJsonString('{"theme":{"color":"dark"}}', $json);
    }

    public function test_set_serializes_a_fluent_instance_including_nested_fluent(): void
    {
        $value = new Fluent([
            'theme' => new Fluent(['color' => 'dark', 'font' => new Fluent(['size' => 14])]),
        ]);

        $json = $this->cast->set($this->model, 'settings', $value, []);

        $this->assertJsonStringEqualsJsonString(
            '{"theme":{"color":"dark","font":{"size":14}}}',
            $json,
        );
    }

    public function test_get_then_set_round_trips_the_original_structure(): void
    {
        $original = '{"theme":{"color":"dark","font":{"size":14}},"tags":["a","b"]}';

        $wrapped = $this->cast->get($this->model, 'settings', $original, []);
        $reencoded = $this->cast->set($this->model, 'settings', $wrapped, []);

        $this->assertJsonStringEqualsJsonString($original, $reencoded);
    }
}
