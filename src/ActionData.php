<?php

namespace Programm011\Actiondata;

use stdClass;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

abstract class ActionData implements ActionDataContract
{
    /**
     * @var \Illuminate\Contracts\Validation\Validator
     */
    private \Illuminate\Contracts\Validation\Validator $validator;

    /**
     * @var array
     */
    protected array $rules = [];

    /**
     * @return array
     */
    abstract public function rules(): array;

    /**
     * @var array
     */
    protected array $attributes = [];

    /**
     * @return array
     */
    public function attributes(): array
    {
        return [];
    }

    /**
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @var Container
     */
    protected Container $container;

    /**
     * Set the container instance.
     *
     * @param Container $container
     *
     * @return $this
     */
    public function setContainer(Container $container): static
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Get the container instance.
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * @param array $parameters
     *
     * @return static
     * @throws \Exception
     */
    public static function createFromArray(array $parameters = []): self
    {
        $instance = new static;
        $instance->setContainer(app());

        try {
            $class = new \ReflectionClass(static::class);

            $fields = [];

            foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $reflectionProperty) {
                if ($reflectionProperty->isStatic()) {
                    continue;
                }

                $field = $reflectionProperty->getName();

                $fields[$field] = $reflectionProperty;
            }

            foreach ($fields as $field => $validator) {
                $value = ($parameters[$field] ?? $parameters[Str::snake($field)] ?? $validator->getDefaultValue() ?? $instance->{$field} ?? null);

                $instance->{$field} = $value;

                unset($parameters[$field]);
            }

            foreach ($instance->attributes() as $attribute => $validation) {
                if (isset($parameters[$attribute])) {
                    $instance->attributes[$attribute] = $parameters[$attribute];
                }
            }
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }

        $instance->prepare();

        return $instance;
    }

    protected function prepare() { }

    /**
     * @param Request $request
     *
     * @return static
     * @throws ValidationException
     * @throws \Exception
     */
    public static function createFromRequest(Request $request): self
    {
        $action = static::createFromArray($request->all());
        $action->validate();

        return $action;
    }

    /**
     * @param $name
     * @param $value
     *
     * @return void
     */
    public function addValidationRule($name, $value): void
    {
        $this->rules[$name] = $value;
    }

    /**
     * @param bool $silent
     *
     * @return bool
     * @throws ValidationException
     */
    public function validate(bool $silent = true): bool
    {
        $this->validator = Validator::make($this->toArray(true), $this->getRules(), $this->getValidationMessages(), $this->getValidationAttributes());
        if ($silent && $this->validator->fails()) {
            throw new ValidationException($this->validator);
        }
        $this->validator->validate();

        return true;
    }

    /**
     * @return MessageBag|null
     */
    public function getValidationErrors(): ?MessageBag
    {
        return $this->validator->errors();
    }

    /**
     * @return array
     */
    public function getValidationMessages(): array
    {
        return trans('validation');
    }

    /**
     * @return array
     */
    public function getRules(): array
    {
        return array_merge($this->attributes(), $this->rules(), $this->rules);
    }

    /**
     * @return array
     */
    public function getValidationAttributes(): array
    {
        return [];
    }

    /**
     * @return array
     */
    public function toArray(bool $excludeEmpty = false): array
    {
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        $data       = [];

        foreach ($properties as $property) {
            $name  = $property->getName();
            $value = $this->$name;

            if ($excludeEmpty && empty($value)) {
                continue;
            }

            $data[$name] = $value;
        }

        return array_merge($this->attributes, $data);
    }

    /**
     * @param array $data
     */
    public function fill(array $data)
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * @param string $key
     *
     * @return mixed|null
     */
    public function __get(string $key)
    {
        if (property_exists($this, $key)) {
            return $this->$key;
        }

        return null;
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function __set(string $key, $value)
    {
        if (property_exists($this, $key)) {
            $this->$key = $value;
        }
    }

    /**
     * @param bool $trim_nulls
     *
     * @return array
     * @throws \Exception
     */
    public function toSnakeArray(bool $trim_nulls = false): array
    {
        $data = [];

        try {
            $class = new \ReflectionClass(static::class);

            $properties = $class->getProperties(\ReflectionProperty::IS_PUBLIC);

            foreach ($properties as $reflectionProperty) {
                if ($reflectionProperty->isStatic()) {
                    continue;
                }

                $value = $reflectionProperty->getValue($this);

                if ($trim_nulls === true) {
                    if (!is_null($value)) {
                        $data[Str::snake($reflectionProperty->getName())] = $value;
                    }
                } else {
                    $data[Str::snake($reflectionProperty->getName())] = $value;
                }
            }
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }

        return $data;
    }

    /**
     * @param $keys
     *
     * @return array
     * @throws \Exception
     */
    public function only($keys): array
    {
        $results = [];

        $input = $this->validated();

        $placeholder = new stdClass;

        foreach (is_array($keys) ? $keys : func_get_args() as $key) {
            $value           = data_get($input, $key, $placeholder);
            $attributesValue = data_get($this->attributes, $key, $placeholder);

            if ($value !== $placeholder) {
                Arr::set($results, $key, $value);
            }

            if ($attributesValue !== $placeholder) {
                Arr::set($results, $key, $attributesValue);
            }
        }

        return $results;
    }

    /**
     * @throws ValidationException
     */
    public function validated($key = null, $default = null): array
    {
        $data = [];

        foreach ($this->validator->validated() as $key => $value) {
            $data[Str::snake($key)] = $value;
        }

        return $data;
    }

    /**
     * @param $key
     *
     * @return bool
     * @throws \Exception
     */
    public function has($key): bool
    {
        $keys = is_array($key) ? $key : func_get_args();

        $input = $this->validated();

        foreach ($keys as $value) {
            if (Arr::has($input, $value) && Arr::get($input, $value) == null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $key
     * @param mixed|null $default
     *
     * @return mixed
     * @throws \Exception
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->toSnakeArray()[$key] ?? $default;
    }
}
