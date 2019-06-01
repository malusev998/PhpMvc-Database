<?php

use Dusan\PhpMvc\Database\CustomInsert;
use Dusan\PhpMvc\Database\Model;

class User extends Model implements CustomInsert
{
    protected $name;
    protected $surname;

    public function setInsert(): string
    {
        return "INSERT INTO users(name, surname) VALUES (:name, :surname);";
    }

    public function setInsertBindings(): array
    {
        return [
            ':name' => $this->name,
            ':surname' => $this->surname,
        ];
    }
}