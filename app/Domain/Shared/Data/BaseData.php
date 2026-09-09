<?php

declare(strict_types=1);

namespace App\Domain\Shared\Data;

use App\Domain\Shared\Data\Attributes\Derived;
use App\Domain\Shared\Data\Contracts\HasEnumLabels;
use BackedEnum;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Carbon;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Spatie\LaravelData\Data;
use UnitEnum;

abstract class BaseData extends Data
{
    /**
     * @return array<int, ReflectionProperty>
     */
    protected function getPublicProperties(): array
    {
        $reflection = new ReflectionClass($this);

        return $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
    }

    /**
     * @return array<int, ReflectionProperty>
     */
    protected static function getPublicPropertiesFromClass(): array
    {
        $reflection = new ReflectionClass(static::class);

        return $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
    }

    /**
     * @return array<int, string>
     */
    protected function getRelationProperties(): array
    {
        return $this->getPropertiesByType(Data::class);
    }

    /**
     * @return array<int, string>
     */
    protected function getEnumProperties(): array
    {
        return $this->getPropertiesByType(UnitEnum::class);
    }

    /**
     * @return array<int, string>
     */
    protected static function getEnumPropertiesFromClass(): array
    {
        return static::getPropertiesByTypeFromClass(UnitEnum::class);
    }

    /**
     * @return array<int, string>
     */
    protected function getPropertiesByType(string $typeClass): array
    {
        $properties = [];
        foreach ($this->getPublicProperties() as $property) {
            if (! $this->isPropertyOfType($property, $typeClass)) {
                continue;
            }

            $properties[] = $property->getName();
        }

        return $properties;
    }

    /**
     * @return array<int, string>
     */
    protected static function getPropertiesByTypeFromClass(string $typeClass): array
    {
        $properties = [];
        foreach (static::getPublicPropertiesFromClass() as $property) {
            if (! static::isPropertyOfTypeStatic($property, $typeClass)) {
                continue;
            }

            $properties[] = $property->getName();
        }

        return $properties;
    }

    protected function isPropertyOfType(ReflectionProperty $property, string $typeClass): bool
    {
        $type = $property->getType();
        if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
            $typeName = $type->getName();

            return class_exists($typeName) && is_a($typeName, $typeClass, true);
        }

        return false;
    }

    protected static function isPropertyOfTypeStatic(ReflectionProperty $property, string $typeClass): bool
    {
        $type = $property->getType();
        if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
            $typeName = $type->getName();

            return class_exists($typeName) && is_a($typeName, $typeClass, true);
        }

        return false;
    }

    /**
     * @return array{value: string|int, label: string, colorClass: string|null, options: array<int, array{value: string|int, label: string, colorClass: string|null}>}
     */
    protected function enumToArray(UnitEnum $enum): array
    {
        $enumClass = get_class($enum);
        $value = $enum instanceof BackedEnum ? $enum->value : $enum->name;

        /** @var (HasEnumLabels&UnitEnum)|null $labeledEnum */
        $labeledEnum = $enum instanceof HasEnumLabels ? $enum : null;

        return [
            'value' => $value,
            'label' => $labeledEnum ? $labeledEnum->label() : (string) $value,
            'colorClass' => $labeledEnum ? $labeledEnum->colorClass() : null,
            'options' => collect($enumClass::cases())->map(static function (UnitEnum $case): array {
                /** @var UnitEnum $case */
                $caseValue = $case instanceof BackedEnum ? $case->value : $case->name;

                /** @var (HasEnumLabels&UnitEnum)|null $labeledCase */
                $labeledCase = $case instanceof HasEnumLabels ? $case : null;

                return [
                    'value' => $caseValue,
                    'label' => $labeledCase ? $labeledCase->label() : (string) $caseValue,
                    'colorClass' => $labeledCase ? $labeledCase->colorClass() : null,
                ];
            })->toArray(),
        ];
    }

    protected function getEnumValueForStorage(UnitEnum $enum): string|int
    {
        return $enum instanceof BackedEnum ? $enum->value : $enum->name;
    }

    /**
     * Normalize a wire timestamp to the app timezone's `Y-m-d H:i:s` for raw
     * DB upserts. iOS devices send UTC instants (`...Z`); persisting their
     * wall time verbatim would land them two hours behind server-stamped
     * rows, which made freshly-taken orders look long unattended on the
     * floor plan. Server-originated ISO strings already carry the app
     * offset, so for those this is a no-op.
     */
    protected static function toDbDateTime(?string $value): ?string
    {
        return $value !== null ? Carbon::parse($value)->setTimezone(config('app.timezone'))->toDateTimeString() : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toUpdateArray(): array
    {
        $data = $this->toArray();
        $relations = static::getRelationPropertiesFromClass();

        foreach ($this->getEnumProperties() as $enumProperty) {
            $enumValue = $this->{$enumProperty};
            $data[$enumProperty] = $enumValue instanceof UnitEnum ? $this->getEnumValueForStorage($enumValue) : null;
        }

        return collect($data)->except($relations)->except(static::getDerivedPropertiesFromClass())->except('id')->toArray();
    }

    /**
     * Properties marked #[Derived]: read from the model, never written back.
     *
     * @return array<int, string>
     */
    protected static function getDerivedPropertiesFromClass(): array
    {
        $properties = [];
        foreach (static::getPublicPropertiesFromClass() as $property) {
            if ($property->getAttributes(Derived::class) !== []) {
                $properties[] = $property->getName();
            }
        }

        return $properties;
    }

    /**
     * @param  Arrayable<int|string, mixed>|array<string, mixed>  $payload
     */
    public static function validateAndCreate(Arrayable|array $payload): static
    {
        $payload = $payload instanceof Arrayable ? $payload->toArray() : $payload;

        foreach (static::getEnumPropertiesFromClass() as $enumProperty) {
            if (
                ! (
                    isset($payload[$enumProperty])
                    && is_array($payload[$enumProperty])
                    && isset($payload[$enumProperty]['value'])
                )
            ) {
                continue;
            }

            $payload[$enumProperty] = $payload[$enumProperty]['value'];
        }

        $relations = static::getRelationPropertiesFromClass();
        $payload = collect($payload)->except($relations)->toArray();

        return parent::validateAndCreate($payload);
    }

    /**
     * @return array<int, string>
     */
    protected static function getRelationPropertiesFromClass(): array
    {
        $properties = static::getPropertiesByTypeFromClass(Data::class);

        $reflection = new ReflectionClass(static::class);
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $type = $property->getType();

            if (
                $type instanceof ReflectionNamedType
                && ($type->getName() === 'Illuminate\Support\Collection' || $type->getName() === 'Collection')
            ) {
                $docComment = $property->getDocComment();
                if ($docComment && preg_match('/@var\s+.*Collection<.*,\s*(\w+Data).*>/', $docComment, $matches)) {
                    $properties[] = $property->getName();
                }
            }
        }

        return array_unique($properties);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string>|null  $except
     * @param  array<string>|null  $only
     * @return array<string, mixed>
     */
    public static function empty(
        array $extra = [],
        mixed $replaceNullValuesWith = null,
        ?array $except = null,
        ?array $only = null,
    ): array {
        $reflection = new ReflectionClass(static::class);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        // A promoted property with a default (`string $currency = 'EUR'`,
        // `bool $is_active = true`) starts a new record with that default,
        // not with the empty value of its type.
        $constructorDefaults = [];
        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            if ($parameter->isDefaultValueAvailable()) {
                $constructorDefaults[$parameter->getName()] = $parameter->getDefaultValue();
            }
        }

        $data = [];
        foreach ($properties as $property) {
            $name = $property->getName();

            if (array_key_exists($name, $constructorDefaults)) {
                $data[$name] = $constructorDefaults[$name];

                continue;
            }

            $type = $property->getType();
            $isNullable = $type?->allowsNull() ?? false;
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;

            $value = match ($typeName) {
                'string' => $isNullable ? null : '',
                'int' => $isNullable ? null : 0,
                'bool' => $isNullable ? null : false,
                'float' => $isNullable ? null : 0.0,
                'array' => $isNullable ? null : [],
                default => null,
            };

            if ($typeName && class_exists($typeName) && is_subclass_of($typeName, UnitEnum::class)) {
                $cases = $typeName::cases();
                if ($cases !== []) {
                    $value = $cases[0];
                }
            }

            $data[$name] = $value;
        }

        $emptyData = array_merge($data, $extra);

        if ($only !== null) {
            $emptyData = array_intersect_key($emptyData, array_flip($only));
        }

        if ($except !== null) {
            $emptyData = array_diff_key($emptyData, array_flip($except));
        }

        return $emptyData;
    }

    public static function fromEmpty(): static
    {
        return static::from(static::empty());
    }
}
