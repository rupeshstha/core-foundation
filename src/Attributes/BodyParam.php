<?php

namespace CoreFoundation\Attributes;

/**
 * BodyParam
 *
 * Fluent descriptor for a single request body field.
 * Used inside BaseRequest::schema() to describe request fields for future doc generation.
 *
 * NOT a PHP Attribute — it's a plain fluent class returned from schema().
 * This is intentional: FormRequest rules live in methods, not typed properties,
 * so there's no property to attach a PHP #[Attribute] to. schema() returning
 * BodyParam instances is the clean, readable, refactorable equivalent.
 *
 * USAGE (inside BaseRequest::schema()):
 *
 *   public function schema(): array
 *   {
 *       return [
 *           BodyParam::make('email')
 *               ->type('string')
 *               ->format('email')
 *               ->description('The user\'s email address.')
 *               ->example('user@example.com')
 *               ->required(),
 *
 *           BodyParam::make('role')
 *               ->type('string')
 *               ->description('The user\'s role in the system.')
 *               ->example('editor')
 *               ->optional()
 *               ->enum(['admin', 'editor', 'viewer']),
 *
 *           BodyParam::make('age')
 *               ->type('integer')
 *               ->description('User age in years.')
 *               ->example(28)
 *               ->optional()
 *               ->minimum(18)
 *               ->maximum(120),
 *       ];
 *   }
 *
 * FUTURE DOC GENERATION:
 *   A command will call $request->schema() and map each BodyParam to an
 *   OpenAPI requestBody property — no change to this class needed.
 */
final class BodyParam
{
    private string $name;

    private string $type = 'string';

    private string $format = '';

    private string $description = '';

    private mixed $example = null;

    private bool $required = true;

    private array $enum = [];

    private ?int $minimum = null;

    private ?int $maximum = null;

    private string $items = '';

    private function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    /**
     * OpenAPI-compatible type: 'string' | 'integer' | 'number' | 'boolean' | 'array' | 'object'
     */
    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * OpenAPI format hint: 'date-time' | 'date' | 'email' | 'uuid' | 'uri' | 'password'
     */
    public function format(string $format): self
    {
        $this->format = $format;

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function example(mixed $example): self
    {
        $this->example = $example;

        return $this;
    }

    public function required(): self
    {
        $this->required = true;

        return $this;
    }

    public function optional(): self
    {
        $this->required = false;

        return $this;
    }

    /** Allowed values — maps to OpenAPI 'enum'. */
    public function enum(array $values): self
    {
        $this->enum = $values;

        return $this;
    }

    /** For numeric types — minimum allowed value. */
    public function minimum(int $min): self
    {
        $this->minimum = $min;

        return $this;
    }

    /** For numeric types — maximum allowed value. */
    public function maximum(int $max): self
    {
        $this->maximum = $max;

        return $this;
    }

    /** For type='array' — the type of each item e.g. 'string', 'integer'. */
    public function items(string $type): self
    {
        $this->items = $type;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getExample(): mixed
    {
        return $this->example;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function getEnum(): array
    {
        return $this->enum;
    }

    public function getMinimum(): ?int
    {
        return $this->minimum;
    }

    public function getMaximum(): ?int
    {
        return $this->maximum;
    }

    public function getItems(): string
    {
        return $this->items;
    }

    /**
     * Export as an OpenAPI-compatible array.
     * Future doc generation calls this directly — no transformation needed.
     */
    public function toOpenApi(): array
    {
        $schema = ['type' => $this->type];

        if ($this->format) {
            $schema['format'] = $this->format;
        }
        if ($this->description) {
            $schema['description'] = $this->description;
        }
        if ($this->example) {
            $schema['example'] = $this->example;
        }
        if ($this->enum) {
            $schema['enum'] = $this->enum;
        }
        if ($this->minimum) {
            $schema['minimum'] = $this->minimum;
        }
        if ($this->maximum) {
            $schema['maximum'] = $this->maximum;
        }
        if ($this->items) {
            $schema['items'] = ['type' => $this->items];
        }

        return [
            'name' => $this->name,
            'required' => $this->required,
            'schema' => $schema,
        ];
    }
}
