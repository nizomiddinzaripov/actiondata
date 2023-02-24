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
     * @param array $parameters
     *
     * @return static
     * @throws BindingResolutionException
     */
    public static function createFromArray(array $parameters = []): self
    {
        $instance = new static;

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
    protected function getValidationMessages(): array
    {
        return trans('validation');
    }

    /**
     * @return array
     */
    protected function getValidationAttributes(): array
    {
        return trans('validation');
    }

    /**
     * @param bool $trim_nulls
     *
     * @return array
     */
    public function toArray(bool $trim_nulls = false): array
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
                        $data[$reflectionProperty->getName()] = $value;
                    }
                } else {
                    $data[$reflectionProperty->getName()] = $value;
                }
            }
        } catch (\Exception $exception) {
        }

        return $data;
    }

    /**
     * @param bool $trim_nulls
     *
     * @return array
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
        }

        return $data;
    }
}
