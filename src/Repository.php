<?php

namespace GridPrinciples\Repositorio;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use GridPrinciples\Repositorio\Exceptions\InvalidModelException;
use GridPrinciples\Repositorio\Exceptions\InvalidResponseDataException;
use GridPrinciples\Repositorio\Exceptions\ModelNotSetException;

class Repository
{
    /**
     * The fully-qualified name of the model.
     * i.e. \App\User::class or "App\User".
     * @var string
     */
    protected static $model;

    /**
     * Construct this repository.
     */
    public function __construct()
    {
        static::boot();
    }

    /**
     * "Boot up" this repository.
     *
     * @throws ModelNotSetException
     */
    protected static function boot()
    {
        if (!static::$model) {
            throw new ModelNotSetException('Model not set.  Please set `protected static $model = \App\YourModel::class;` in ' . get_class($this) . '.');
        }
    }

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
        $query = $this->getNewQuery();

        // Limit to these keys
        $query->whereIn(static::getNewModel()->getKeyName(), $targets);

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
        $query = $this->getNewQuery();

        if ($this->modelHasTrait('Sortable')) {
            // Model can be sorted, so sort it.  Sort information is pulled from the $_GET array.
            $query->sorted();
        }

        // Paginate the results.
        return $query->paginate($limit);
    }

    /**
     * Creates or updates one or many models.
     *
     * @param mixed $response
     * @param mixed|false $target
     * @throws InvalidResponseDataException
     * @return Collection
     */
    public function save($response, $target = false)
    {
        // Are we only saving a single model?  The return should reflect this later.
        $savingOnlyOne = !$target || class_basename($target) === class_basename(static::getNewModel());

        if (!$target) {
            // No target was passed; new up an instance of the default model.
            $target = static::getNewModel();
        }

        $targets = $this->consolidateToCollection($target);

        if (is_object($response)) {
            if (!method_exists($response, 'toArray')) {
                throw new InvalidResponseDataException;
            }

            // Simplify the response object to an array of values to save.
            $response = $response->toArray();
        }

        foreach ($targets as $k => $model) {
            // Save this model with these fields.
            $targets[$k] = $this->saveOne($model, $response);
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
        $targets = $this->consolidateToCollection($target);

        return (bool) static::getNewModel()->destroy($targets->modelKeys());
    }

    /**
     * Create a blank (non-existent) instance of this repository's model.
     *
     * @return mixed
     */
    public static function getNewModel()
    {
        return with(new static::$model)->newInstance();
    }

    /**
     * Create a new query from our model.
     *
     * @return mixed
     */
    public static function getNewQuery()
    {
        $model = static::getNewModel();

        return $model->newQuery();
    }

    /**
     * Saves one given instance of Model with the given array of data.
     *
     * @param $model
     * @param $fields
     * @return boolean
     */
    protected function saveOne($model, $fields)
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
    protected function consolidateToCollection($target)
    {
        if (class_basename($target) == 'Collection') {
            // This is already a Collection, just pass it back.
            return $target;
        }

        if (class_basename($target) !== class_basename(static::getNewModel())) {
            // This wasn't already a Collection, but neither is it the expected class.
            throw new InvalidModelException;
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
        $traitClassNames = array_map('class_basename', class_uses(static::getNewModel()));

        return in_array($string, $traitClassNames);
    }
}
