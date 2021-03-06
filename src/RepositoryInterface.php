<?php

namespace GridPrinciples\Repository;

interface RepositoryInterface
{
    /**
     * Retrieve a target (or targets) by ID.
     *
     * @param $targets
     */
    public function get($targets);

    /**
     * Get a list of records.
     *
     * @param int $limit
     * @return
     */
    public function index($limit = 15);

    /**
     * Creates or updates one or many models.
     *
     * @param mixed $data
     * @param mixed $targets
     * @return Collection
     */
    public function save($data, $targets = false);

    /**
     * Delete one or many models.
     *
     * @param mixed $targets
     * @return boolean
     */
    public function delete($targets);
}
