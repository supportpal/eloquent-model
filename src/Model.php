<?php declare(strict_types=1);

namespace Jenssegers\Model;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Str;
use JsonSerializable;

use function array_combine;
use function array_diff;
use function array_diff_key;
use function array_flip;
use function array_intersect_key;
use function array_key_exists;
use function array_map;
use function array_merge;
use function call_user_func_array;
use function count;
use function func_get_args;
use function get_class;
use function get_class_methods;
use function implode;
use function in_array;
use function is_array;
use function json_decode;
use function json_encode;
use function lcfirst;
use function method_exists;
use function preg_match_all;
use function strtolower;
use function trim;
use function with;

/**
 * @implements ArrayAccess<string, mixed>
 * @implements Arrayable<string, mixed>
 */
abstract class Model implements ArrayAccess, Arrayable, Jsonable, JsonSerializable
{
    /**
     * The model's attributes.
     *
     * @var mixed[]
     */
    protected array $attributes = [];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var string[]
     */
    protected array $hidden = [];

    /**
     * The attributes that should be visible in arrays.
     *
     * @var string[]
     */
    protected array $visible = [];

    /**
     * The accessors to append to the model's array form.
     *
     * @var string[]
     */
    protected array $appends = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected array $fillable = [];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var string[]
     */
    protected array $guarded = [];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array<string, mixed>
     */
    protected array $casts = [];

    /**
     * Indicates whether attributes are snake cased on arrays.
     */
    public static bool $snakeAttributes = true;

    /**
     * Indicates if all mass assignment is enabled.
     */
    protected static bool $unguarded = false;

    /**
     * The cache of the mutated attributes for each class.
     *
     * @var mixed[]
     */
    protected static array $mutatorCache = [];

    /**
     * Create a new Eloquent model instance.
     *
     * @param mixed[] $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param mixed[] $attributes
     * @throws MassAssignmentException
     */
    public function fill(array $attributes): self
    {
        $totallyGuarded = $this->totallyGuarded();

        foreach ($this->fillableFromArray($attributes) as $key => $value) {
            // The developers may choose to place some attributes in the "fillable"
            // array, which means only those attributes may be set through mass
            // assignment to the model, and all others will just be ignored.
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            } elseif ($totallyGuarded) {
                throw new MassAssignmentException($key);
            }
        }

        return $this;
    }

    /**
     * Fill the model with an array of attributes. Force mass assignment.
     *
     * @param mixed[] $attributes
     */
    public function forceFill(array $attributes): self
    {
        // Since some versions of PHP have a bug that prevents it from properly
        // binding the late static context in a closure, we will first store
        // the model in a variable, which we will then use in the closure.
        $model = $this;

        return static::unguarded(
            function () use ($model, $attributes) {
                return $model->fill($attributes);
            }
        );
    }

    /**
     * Get the fillable attributes of a given array.
     *
     * @param mixed[] $attributes
     * @return mixed[]
     */
    protected function fillableFromArray(array $attributes): array
    {
        if (count($this->fillable) > 0 && ! static::$unguarded) {
            return array_intersect_key($attributes, array_flip($this->fillable));
        }

        return $attributes;
    }

    /**
     * Create a new instance of the given model.
     *
     * @param  mixed[] $attributes
     * @return Model
     */
    public function newInstance(array $attributes = []): self
    {
        return new static((array) $attributes);
    }

    /**
     * Create a collection of models from plain arrays.
     *
     * @param mixed[] $items
     * @return mixed[]
     */
    public static function hydrate(array $items): array
    {
        $instance = new static;

        return array_map(
            function ($item) use ($instance) {
                return $instance->newInstance($item);
            },
            $items
        );
    }

    /**
     * Get the hidden attributes for the model.
     *
     * @return string[]
     */
    public function getHidden(): array
    {
        return $this->hidden;
    }

    /**
     * Set the hidden attributes for the model.
     *
     * @param string[] $hidden
     */
    public function setHidden(array $hidden): self
    {
        $this->hidden = $hidden;

        return $this;
    }

    /**
     * Add hidden attributes for the model.
     *
     * @param string|string[]|null $attributes
     */
    public function addHidden(string|array|null $attributes = null): void
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();

        $this->hidden = array_merge($this->hidden, $attributes);
    }

    /**
     * Make the given, typically hidden, attributes visible.
     *
     * @param string[]|string $attributes
     */
    public function withHidden(array|string $attributes): self
    {
        $this->hidden = array_diff($this->hidden, (array) $attributes);

        return $this;
    }

    /**
     * Get the visible attributes for the model.
     *
     * @return string[]
     */
    public function getVisible()
    {
        return $this->visible;
    }

    /**
     * Set the visible attributes for the model.
     *
     * @param string[] $visible
     */
    public function setVisible(array $visible): self
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * Add visible attributes for the model.
     *
     * @param string|string[]|null $attributes
     */
    public function addVisible(string|array|null $attributes = null): void
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();

        $this->visible = array_merge($this->visible, $attributes);
    }

    /**
     * Set the accessors to append to model arrays.
     *
     * @param string[] $appends
     */
    public function setAppends(array $appends): self
    {
        $this->appends = $appends;

        return $this;
    }

    /**
     * Get the fillable attributes for the model.
     *
     * @return string[]
     */
    public function getFillable(): array
    {
        return $this->fillable;
    }

    /**
     * Set the fillable attributes for the model.
     *
     * @param string[] $fillable
     */
    public function fillable(array $fillable): self
    {
        $this->fillable = $fillable;

        return $this;
    }

    /**
     * Get the guarded attributes for the model.
     *
     * @return string[]
     */
    public function getGuarded(): array
    {
        return $this->guarded;
    }

    /**
     * Set the guarded attributes for the model.
     *
     * @param string[] $guarded
     */
    public function guard(array $guarded): self
    {
        $this->guarded = $guarded;

        return $this;
    }

    /**
     * Disable all mass assignable restrictions.
     *
     * @param bool $state
     */
    public static function unguard(bool $state = true): void
    {
        static::$unguarded = $state;
    }

    /**
     * Enable the mass assignment restrictions.
     */
    public static function reguard(): void
    {
        static::$unguarded = false;
    }

    /**
     * Determine if current state is "unguarded".
     */
    public static function isUnguarded(): bool
    {
        return static::$unguarded;
    }

    /**
     * Run the given callable while being unguarded.
     *
     * @return mixed
     */
    public static function unguarded(callable $callback)
    {
        if (static::$unguarded) {
            return $callback();
        }

        static::unguard();

        $result = $callback();

        static::reguard();

        return $result;
    }

    /**
     * Determine if the given attribute may be mass assigned.
     */
    public function isFillable(string $key): bool
    {
        if (static::$unguarded) {
            return true;
        }

        // If the key is in the "fillable" array, we can of course assume that it's
        // a fillable attribute. Otherwise, we will check the guarded array when
        // we need to determine if the attribute is black-listed on the model.
        if (in_array($key, $this->fillable)) {
            return true;
        }

        if ($this->isGuarded($key)) {
            return false;
        }

        return empty($this->fillable);
    }

    /**
     * Determine if the given key is guarded.
     */
    public function isGuarded(string $key): bool
    {
        return in_array($key, $this->guarded) || $this->guarded === ['*'];
    }

    /**
     * Determine if the model is totally guarded.
     */
    public function totallyGuarded(): bool
    {
        return count($this->fillable) === 0 && $this->guarded === ['*'];
    }

    /**
     * Convert the model instance to JSON.
     *
     * @param int $options
     * @return string|false
     */
    public function toJson(int $options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return mixed[]
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Convert the model instance to an array.
     *
     * @return mixed[]
     */
    public function toArray(): array
    {
        return $this->attributesToArray();
    }

    /**
     * Convert the model's attributes to an array.
     *
     * @return mixed[]
     */
    public function attributesToArray(): array
    {
        $attributes = $this->getArrayableAttributes();

        $mutatedAttributes = $this->getMutatedAttributes();

        // We want to spin through all the mutated attributes for this model and call
        // the mutator for the attribute. We cache off every mutated attributes so
        // we don't have to constantly check on attributes that actually change.
        foreach ($mutatedAttributes as $key) {
            if (! array_key_exists($key, $attributes)) {
                continue;
            }

            $attributes[$key] = $this->mutateAttributeForArray(
                $key,
                $attributes[$key]
            );
        }

        // Next we will handle any casts that have been setup for this model and cast
        // the values to their appropriate type. If the attribute has a mutator we
        // will not perform the cast on those attributes to avoid any confusion.
        foreach ($this->casts as $key => $value) {
            if (! array_key_exists($key, $attributes)
                || in_array($key, $mutatedAttributes)
            ) {
                continue;
            }

            $attributes[$key] = $this->castAttribute(
                $key,
                $attributes[$key]
            );
        }

        // Here we will grab all of the appended, calculated attributes to this model
        // as these attributes are not really in the attributes array, but are run
        // when we need to array or JSON the model for convenience to the coder.
        foreach ($this->getArrayableAppends() as $key) {
            $attributes[$key] = $this->mutateAttributeForArray($key, null);
        }

        return $attributes;
    }

    /**
     * Get an attribute array of all arrayable attributes.
     *
     * @return string[]
     */
    protected function getArrayableAttributes(): array
    {
        return $this->getArrayableItems($this->attributes);
    }

    /**
     * Get all of the appendable values that are arrayable.
     *
     * @return string[]
     */
    protected function getArrayableAppends(): array
    {
        if (! count($this->appends)) {
            return [];
        }

        return $this->getArrayableItems(
            array_combine($this->appends, $this->appends)
        );
    }

    /**
     * Get an attribute array of all arrayable values.
     *
     * @param mixed[] $values
     * @return mixed[]
     */
    protected function getArrayableItems(array $values): array
    {
        if (count($this->getVisible()) > 0) {
            return array_intersect_key($values, array_flip($this->getVisible()));
        }

        return array_diff_key($values, array_flip($this->getHidden()));
    }

    /**
     * Get an attribute from the model.
     */
    public function getAttribute(string $key): mixed
    {
        return $this->getAttributeValue($key);
    }

    /**
     * Get a plain attribute (not a relationship).
     */
    protected function getAttributeValue(string $key): mixed
    {
        $value = $this->getAttributeFromArray($key);

        // If the attribute has a get mutator, we will call that then return what
        // it returns as the value, which is useful for transforming values on
        // retrieval from the model to a form that is more useful for usage.
        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $value);
        }

        // If the attribute exists within the cast array, we will convert it to
        // an appropriate native PHP type dependant upon the associated value
        // given with the key in the pair. Dayle made this comment line up.
        if ($this->hasCast($key)) {
            $value = $this->castAttribute($key, $value);
        }

        return $value;
    }

    /**
     * Get an attribute from the $attributes array.
     */
    protected function getAttributeFromArray(string $key): mixed
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        return null;
    }

    /**
     * Determine if a get mutator exists for an attribute.
     */
    public function hasGetMutator(string $key): bool
    {
        return method_exists($this, 'get'.Str::studly($key).'Attribute');
    }

    /**
     * Get the value of an attribute using its mutator.
     */
    protected function mutateAttribute(string $key, mixed $value): mixed
    {
        return $this->{'get'.Str::studly($key).'Attribute'}($value);
    }

    /**
     * Get the value of an attribute using its mutator for array conversion.
     */
    protected function mutateAttributeForArray(string $key, mixed $value): mixed
    {
        $value = $this->mutateAttribute($key, $value);

        return $value instanceof Arrayable ? $value->toArray() : $value;
    }

    /**
     * Determine whether an attribute should be casted to a native type.
     */
    protected function hasCast(string $key): bool
    {
        return array_key_exists($key, $this->casts);
    }

    /**
     * Determine whether a value is JSON castable for inbound manipulation.
     */
    protected function isJsonCastable(string $key): bool
    {
        $castables = ['array', 'json', 'object', 'collection'];

        return $this->hasCast($key) && in_array($this->getCastType($key), $castables, true);
    }

    /**
     * Get the type of cast for a model attribute.
     */
    protected function getCastType(string $key): string
    {
        return trim(strtolower($this->casts[$key]));
    }

    /**
     * Cast an attribute to a native PHP type.
     */
    protected function castAttribute(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return $value;
        }

        switch ($this->getCastType($key)) {
            case 'int':
            case 'integer':
                return (int) $value;

            case 'real':
            case 'float':
            case 'double':
                return (float) $value;

            case 'string':
                return (string) $value;

            case 'bool':
            case 'boolean':
                return (bool) $value;

            case 'object':
                return $this->fromJson($value, true);

            case 'array':
            case 'json':
                return $this->fromJson($value);

            case 'collection':
                return new BaseCollection($this->fromJson($value));

            default:
                return $value;
        }
    }

    /**
     * Set a given attribute on the model.
     */
    public function setAttribute(string $key, mixed $value): self
    {
        // First we will check for the presence of a mutator for the set operation
        // which simply lets the developers tweak the attribute as it is set on
        // the model, such as "json_encoding" an listing of data for storage.
        if ($this->hasSetMutator($key)) {
            $method = 'set'.Str::studly($key).'Attribute';

            $this->{$method}($value);

            return $this;
        }

        if ($this->isJsonCastable($key) && $value !== null) {
            $value = $this->asJson($value);
        }

        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Determine if a set mutator exists for an attribute.
     */
    public function hasSetMutator(string $key): bool
    {
        return method_exists($this, 'set'.Str::studly($key).'Attribute');
    }

    /**
     * Encode the given value as JSON.
     *
     * @param  mixed $value
     * @return string|false
     */
    protected function asJson(mixed $value)
    {
        return json_encode($value);
    }

    /**
     * Decode the given JSON back into an array or object.
     */
    public function fromJson(string $value, bool $asObject = false): mixed
    {
        return json_decode($value, ! $asObject);
    }

    /**
     * Clone the model into a new, non-existing instance.
     *
     * @param string[]|null $except
     */
    public function replicate(?array $except = null): self
    {
        $except = $except ?: [];

        $attributes = Arr::except($this->attributes, $except);

        return with(new static)->fill($attributes);
    }

    /**
     * Get all of the current attributes on the model.
     *
     * @return mixed[]
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get the mutated attributes for a given instance.
     *
     * @return mixed[]
     */
    public function getMutatedAttributes(): array
    {
        $class = get_class($this);

        if (! isset(static::$mutatorCache[$class])) {
            static::cacheMutatedAttributes($class);
        }

        return static::$mutatorCache[$class];
    }

    /**
     * Extract and cache all the mutated attributes of a class.
     */
    public static function cacheMutatedAttributes(string $class): void
    {
        $mutatedAttributes = [];

        // Here we will extract all of the mutated attributes so that we can quickly
        // spin through them after we export models to their array form, which we
        // need to be fast. This'll let us know the attributes that can mutate.
        if (preg_match_all('/(?<=^|;)get([^;]+?)Attribute(;|$)/', implode(';', get_class_methods($class)), $matches)) {
            foreach ($matches[1] as $match) {
                if (static::$snakeAttributes) {
                    $match = Str::snake($match);
                }

                $mutatedAttributes[] = lcfirst($match);
            }
        }

        static::$mutatorCache[$class] = $mutatedAttributes;
    }

    /**
     * Dynamically retrieve attributes on the model.
     */
    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the model.
     */
    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Determine if the given attribute exists.
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->$offset);
    }

    /**
     * Get the value for a given offset.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->$offset;
    }

    /**
     * Set the value for a given offset.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->$offset = $value;
    }

    /**
     * Unset the value for a given offset.
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->$offset);
    }

    /**
     * Determine if an attribute exists on the model.
     */
    public function __isset(string $key): bool
    {
        return (isset($this->attributes[$key]) || isset($this->relations[$key]))
            || ($this->hasGetMutator($key) && $this->getAttributeValue($key) !== null);
    }

    /**
     * Unset an attribute on the model.
     */
    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }

    /**
     * Handle dynamic static method calls into the method.
     *
     * @param mixed[] $parameters
     * @return mixed
     */
    public static function __callStatic(string $method, array $parameters)
    {
        $instance = new static;

        return call_user_func_array([$instance, $method], $parameters);
    }

    /**
     * Convert the model to its string representation.
     */
    public function __toString()
    {
        $string = $this->toJson();

        if ($string === false) {
            return '';
        }

        return $string;
    }
}
