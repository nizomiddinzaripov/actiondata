<?php

namespace Programm011\Actiondata;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ActionDataBase implements ActionDataContract
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
     * @var \Illuminate\Container\Container
     */
    protected $container;

    /**
     * Set the container instance.
     *
     * @param \Illuminate\Container\Container $container
     *
     * @return $this
     */
    public function setContainer($container)
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Get the container instance.
     *
     * @return \Illuminate\Container\Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * @param array $parameters
     *
     * @return static
     * @throws BindingResolutionException
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
        } catch (\Exception $exception) {
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
     * @throws BindingResolutionException
     */
    public static function createFromRequest(Request $request): self
    {
        $res = static::createFromArray($request->all());
        $res->validate(false);

        return $res;
    }

    public function addValidationRule($name, $value)
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
        $this->validator = Validator::make($this->toArray(true), $this->rules, $this->getValidationMessages(), $this->getValidationAttributes());
        if ($silent) {
            return !$this->validator->fails();
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
        return [];
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
        $data = [];

        foreach ($properties as $property) {
            $name = $property->getName();
            $value = $this->$name;

            if ($excludeEmpty && empty($value)) {
                continue;
            }

            $data[$name] = $value;
        }

        return $data;
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
}
