<?php

namespace GridPrinciples\Repository;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use GridPrinciples\Repository\Exceptions\InvalidModelException;
use GridPrinciples\Repository\Exceptions\InvalidResponseDataException;

abstract class EloquentRepository implements RepositoryInterface
{
    /**
     * The fully-qualified name of the model.
     * i.e. \App\User::class or "App\User".
     * @var string
     */
    protected static $model;

    /**
     * Retrieve a target (or targets) by ID.
     *
     * @param $targets
     */
    public function get($targets)
    {
        $singleModelMode = false;

        if (!is_array($targets)) {
            // Only one was passed, so only one will be returned.
            $singleModelMode = true;

            // Cast as array
            $targets = [$targets];
        }

        // Start crafting a new query.
        $query = static::newQuery();

        // Limit to these keys
        $query->whereIn(static::newModel()->getKeyName(), $targets);

        // Run the query and return the results.
        return $singleModelMode ? $query->first() : $query->get();
    }

    /**
     * Gets a paginated set of records, sorted (if applicable).
     *
     * @param int $limit
     * @return
     */
    public function index($limit = 15)
    {
        // Start crafting a new query.
        $query = self::newQuery();

        if (self::modelHasTrait('Sortable')) {
            // Model can be sorted, so sort it.  Sort information is pulled from the $_GET array.
            $query->sorted();
        }

        // Paginate the results.
        return $query->paginate($limit);
    }

    /**
     * Creates or updates one or many models.
     *
     * @param mixed $data
     * @param mixed $targets
     * @throws InvalidResponseDataException
     * @return Collection
     */
    public function save($data, $targets = false)
    {
        if (is_array($targets)) {
            // If the $targets is a basic array, put it into a Collection.
            $targets = collect($targets);
        }

        // Are we only saving a single model?  The return should reflect this later.
        $savingOnlyOne = !$targets || class_basename($targets) === class_basename(static::newModel());

        if (!$targets) {
            // No target was passed; new up an instance of the default model.
            $targets = static::newModel();
        }

        $targets = static::buildCollection($targets);

        if (is_object($data)) {
            if (!method_exists($data, 'toArray')) {
                throw new InvalidResponseDataException('The data object attempting to be saved cannot be cast as an array.');
            }

            // Simplify the response object to an array of values to save.
            $data = $data->toArray();
        }

        foreach ($targets as $k => $model) {
            // Save this model with these fields.
            $targets[$k] = static::saveOne($model, $data);
        }

        return $savingOnlyOne ? $targets->first() : $targets;
    }

    /**
     * Delete one or many models.
     *
     * @param mixed $target
     * @return boolean
     */
    public function delete($target)
    {
        $targets = static::buildCollection($target);

        return (bool) static::newModel()->destroy($targets->modelKeys());
    }

    /**
     * Create a blank (non-existent) instance of this repository's model.
     *
     * @return mixed
     */
    public static function newModel()
    {
        return with(new static::$model)->newInstance();
    }

    /**
     * Create a new query from our model.
     *
     * @return mixed
     */
    public static function newQuery()
    {
        $model = static::newModel();

        return $model->newQuery();
    }

    /**
     * Saves one given instance of Model with the given array of data.
     *
     * @param $model
     * @param $fields
     * @return boolean
     */
    protected static function saveOne($model, $fields)
    {
        // Set model data from the response data.
        $model->fill($fields);

        // Save the model back to the database.
        $model->save();

        // If successful, return the ID.
        return $model;
    }

    /**
     * Ensure the variable is a Collection.
     *
     * @param mixed $target
     * @return Collection
     * @throws InvalidModelException
     */
    protected static function buildCollection($target)
    {
        if ($target instanceof Collection) {
            // This is already a Collection, just pass it back.
            return $target;
        }

        if (class_basename($target) !== class_basename(static::newModel())) {
            // This wasn't already a Collection, but neither is it the expected class.
            $passed = class_basename($target);
            $expected = class_basename(static::newModel());
            $message = "Model of class $passed does not match repository's expected $expected class.";
            throw new InvalidModelException($message);
        }

        $return = new EloquentCollection;
        $return->add($target);

        return $return;
    }

    /**
     * Determines whether our default model contains a given trait.
     *
     * @param $string
     * @return bool
     */
    protected static function modelHasTrait($string)
    {
        $traitClassNames = array_map('class_basename', class_uses(static::newModel()));

        return in_array($string, $traitClassNames);
    }
}
