<?php

namespace Laravel\Lumen\Routing;

use Closure as BaseClosure;
use Laravel\Lumen\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Validator;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Validation\ValidationException;

trait ProvidesConvenienceMethods
{
    /**
     * The response builder callback.
     *
     * @var \Closure
     */
    protected static $responseBuilder;

    /**
     * The error formatter callback.
     *
     * @var \Closure
     */
    protected static $errorFormatter;

    /**
     * Set the response builder callback.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public static function buildResponseUsing(BaseClosure $callback)
    {
        static::$responseBuilder = $callback;
    }

    /**
     * Set the error formatter callback.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public static function formatErrorsUsing(BaseClosure $callback)
    {
        static::$errorFormatter = $callback;
    }

    /**
     * Validate the given request with the given rules.
     *
     * @param  \Laravel\Lumen\Http\Request  $request
     * @param  array  $rules
     * @param  array  $messages
     * @param  array  $customAttributes
     * @return void
     */
    public function validate(Request $request, array $rules, array $messages = [], array $customAttributes = [])
    {
        $validator = $this->getValidationFactory()->make($request->all(), $rules, $messages, $customAttributes);

        if ($validator->fails()) {
            $this->throwValidationException($request, $validator);
        }
    }

    /**
     * Throw the failed validation exception.
     *
     * @param  \Laravel\Lumen\Http\Request  $request
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     */
    protected function throwValidationException(Request $request, $validator)
    {
        throw new ValidationException($validator, $this->buildFailedValidationResponse(
            $request, $this->formatValidationErrors($validator)
        ));
    }

    /**
     * {@inheritdoc}
     */
    protected function buildFailedValidationResponse(Request $request, array $errors)
    {
        if (isset(static::$responseBuilder)) {
            return call_user_func(static::$responseBuilder, $request, $errors);
        }

        return new JsonResponse($errors, 422);
    }

    /**
     * {@inheritdoc}
     */
    protected function formatValidationErrors(Validator $validator)
    {
        if (isset(static::$errorFormatter)) {
            return call_user_func(static::$errorFormatter, $validator);
        }

        return $validator->errors()->getMessages();
    }

    /**
     * Authorize a given action against a set of arguments.
     *
     * @param  mixed  $ability
     * @param  mixed|array  $arguments
     * @return \Illuminate\Auth\Access\Response
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function authorize($ability, $arguments = [])
    {
        list($ability, $arguments) = $this->parseAbilityAndArguments($ability, $arguments);

        return app(Gate::class)->authorize($ability, $arguments);
    }

    /**
     * Authorize a given action for a user.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable|mixed  $user
     * @param  mixed  $ability
     * @param  mixed|array  $arguments
     * @return \Illuminate\Auth\Access\Response
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function authorizeForUser($user, $ability, $arguments = [])
    {
        list($ability, $arguments) = $this->parseAbilityAndArguments($ability, $arguments);

        return app(Gate::class)->forUser($user)->authorize($ability, $arguments);
    }

    /**
     * Guesses the ability's name if it wasn't provided.
     *
     * @param  mixed  $ability
     * @param  mixed|array  $arguments
     * @return array
     */
    protected function parseAbilityAndArguments($ability, $arguments)
    {
        if (is_string($ability)) {
            return [$ability, $arguments];
        }

        return [debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2]['function'], $ability];
    }

    /**
     * Dispatch a job to its appropriate handler.
     *
     * @param  mixed  $job
     * @return mixed
     */
    public function dispatch($job)
    {
        return app('Illuminate\Contracts\Bus\Dispatcher')->dispatch($job);
    }

    /**
     * Get a validation factory instance.
     *
     * @return \Illuminate\Contracts\Validation\Factory
     */
    protected function getValidationFactory()
    {
        return app('validator');
    }
}
