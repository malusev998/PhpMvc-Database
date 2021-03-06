<?php


namespace BrosSquad\MicroORM\Relations;

/**
 * Interface Relation
 * @api
 * @package BrosSquad\MicroORM\Relations
 */
interface Relation
{
    public function getRelation(): string;
    /**
     * @return mixed
     */
    public function getReferenceTable();

    /**
     * @return mixed
     */
    public function getReferenceKey();

    /**
     * @return mixed
     */
    public function getForeignKey();

    /**
     * @return mixed
     */
    public function getForeignTable();
}
