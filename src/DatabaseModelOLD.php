<?php

namespace Dusan\PhpMvc\Database;

use Dusan\PhpMvc\Database\Traits\DateTimeParse;
use Dusan\PhpMvc\Database\Traits\Delete;
use Dusan\PhpMvc\Database\Traits\Diff;
use Dusan\PhpMvc\Database\Traits\Insert;
use Dusan\PhpMvc\Database\Traits\Lockable;
use Dusan\PhpMvc\Database\Traits\ObjectVariables;
use Dusan\PhpMvc\Database\Traits\Save;
use Dusan\PhpMvc\Database\Traits\Update;
use Exception;
use JsonSerializable;
use Dusan\PhpMvc\Database\FluentApi\Fluent;
use Dusan\PhpMvc\Database\Traits\GetDateTime;
use Dusan\PhpMvc\Database\Traits\JoinArrayByComma;
use PDO;
use PDOException;
use Psr\Container\ContainerInterface;
use Serializable;

/**
 * Abstract DatabaseModelOLD class represents model that is in database
 * Each model that wants to represent table in database must inherit this abstract class
 * it makes use of Fluent api and automatic sql generation for ease of use
 *
 * @package Dusan\PhpMvc\Database
 * @author  Dusan Malusev <dusan.998@outlook.com>
 * @version 2.0
 * @license GPL Version 2
 * @uses    Fluent,JsonSerializable, Driver
 * @method setUpdate()
 * @method setUpdateBindings()
 * @method setInsert()
 * @method setInsertBindings()
 */
abstract class DatabaseModelOLD extends AbstractModel implements JsonSerializable, PdoConstants, Serializable
{
    use Diff;
    use GetDateTime;
    use ObjectVariables;
    use JoinArrayByComma;
    use DateTimeParse;
    use Insert;
    use Update;
    use Delete;
    use Lockable;
    use Save;
    /**
     * @var ContainerInterface
     */
    private static $container;

    protected static $fields = [];

    private $lock = false;

    /**
     * @var null|string
     */
    protected static $observer = null;

    /**
     * @var null|\Dusan\PhpMvc\Database\Events\Observer
     */
    private static $observerInstance = null;

    /**
     * Name of column in database for primary key
     *
     * @source
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Primary key field in database
     *
     * @api
     * @var null|int|string
     */
    protected $id = NULL;

    /**
     * Alias for the table
     *
     * @var string
     */
    protected $alias = '';

    /**
     * Name of the table in the database
     * defaults to __CLASS__ + 's'
     * modified by setTable() method
     *
     * @var string
     * @source
     * @internal
     */
    protected $table = '';

    /**
     * Protected array protects data in the model,
     * this array is empty by default which indicated every property
     * is available for insert update statements
     * if you want to protect field put it in this array
     * @api
     * @var array
     */
    protected $protected = [];

    protected $fillable = [];

    /**
     * When member of class is accessed
     * name of the member is added to $changed
     * for later use with insert() and update() methods
     * <b>Tracks changed for update statement</b>
     * @internal
     * @source
     * @var array
     */
    protected $changed = [];

    /**
     * Bindings of the fields in class with PDO type parameters for better protection
     * Key of the array must be string with name of the field which will have the PDO::PARAM_*
     * Value must be PDO::PARAM_* ->
     * For ease of use Database model will reference these parameters without PDO::PARAM_*
     *
     * @api
     * @var array
     */
    protected $memberTypeBindings = [];

    /**
     * Guarded array restricts the Json serializes from showing
     * it as output
     * <b>Serialization</b>
     * @example "../../docs/Database/restricted.php"
     * @var array
     */
    protected $guarded = [];

    /**
     * Underling database driver
     *
     * @var Driver
     */
    protected static $database;

    /**
     * Setting the database driver
     *
     * @param Driver $database
     *
     * @return void
     */
    private static function setDatabase(Driver $database)
    {
        self::$database = $database;
    }

    /**
     * Setting the IoC Container
     *
     * @param \Psr\Container\ContainerInterface $container
     */
    private static function setContainer(ContainerInterface $container)
    {
        self::$container = $container;
    }

    /**
     * DatabaseModelOLD constructor.
     *
     * @param array $properties
     */
    public function __construct(?array $properties = NULL)
    {
        $this->table = $this->setTable();
        if(static::$fields === NULL) {
            static::$fields = $this->getVariables();
        }
        $this->protected();

        $this->exclude();
        $this->protected = array_flip($this->protected);
        $this->guarded = array_flip($this->guarded);

        if(static::$observer === null) {
            static::$observer = $this->setObserver();
        }
        if (static::$observer !== null) {
            static::$observerInstance = static::$container->get(static::$observer);
        }
        if($properties !== null) {
            foreach ($properties as $name => $value) {
                $this->{$name} = $value;
            }
        }

    }

    /**
     * Gets table name
     *
     * @api
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @param string $name
     *
     * @return mixed
     * @throws \Dusan\PhpMvc\Database\Exceptions\PropertyNotFound
     */
    public function __get(string $name)
    {
        return parent::__get($name);
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @link  https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return $this->excluded();
    }

    /**
     * Setter function
     *
     * @param string $name
     * @param        $value
     *
     * @internal
     * @throws \Dusan\PhpMvc\Database\Exceptions\PropertyNotFound
     */
    public function __set(string $name, $value)
    {
        if (property_exists($this, $name)) {
            if ($name === $this->primaryKey) {
                $this->setId($value);
            } else {
                parent::__set($name, $value);
            }
            $this->hasChanged($name);
        }
    }

    /**
     * Adding the members of class to restricted array
     * @internal
     * @return void
     */
    protected function protected()
    {
        $this->protected[] = 'guarded';
        $this->protected[] = 'table';
        $this->protected[] = 'database';
        $this->protected[] = 'protected';
        $this->protected[] = 'restricted';
        $this->protected[] = 'fillable';
        $this->protected[] = 'changed';
        $this->protected[] = 'memberTypeBindings';
        $this->protected[] = 'alias';
        $this->protected[] = 'primaryKey';
        $this->protected[] = 'id';
        $this->protected[] = 'format';
        $this->protected[] = 'lock';
    }


    /**
     * Adding the member of class that will be excluded
     * in serialization and json encoding
     * @internal
     * @return void
     */
    protected function exclude(): void
    {
        $this->guarded[] = 'guarded';
        $this->guarded[] = 'table';
        $this->guarded[] = 'database';
        $this->guarded[] = 'guarded';
        $this->guarded[] = 'fillable';
        $this->guarded[] = 'changed';
        $this->guarded[] = 'memberTypeBindings';
        $this->guarded[] = 'alias';
        $this->guarded[] = 'primaryKey';
        $this->guarded[] = 'format';
        $this->guarded[] = 'protected';
        $this->guarded[] = 'lock';
    }

    /**
     * Excluding the variable in serialization
     * @internal
     * @return array
     */
    protected function excluded(): array
    {
        $returnArr = [];
        foreach(static::$fields as $item)
        {
            $value = $this->guarded[$item];
            if(!isset($value)) {
                $returnArr[$item] = $value;
            }
        }
        return $returnArr;
    }

    /**
     * Returns the value of the primary key from database
     * @api
     * @return int|string|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Gets alias for the table
     * @api
     * @return string
     */
    public function getAlias(): string
    {
        return $this->alias;
    }

    /**
     * Sets the values of the primary key
     * <b> If you want setId to record the changed on the id override it on child class</b>
     * @api
     * @param int|string $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * Making some function that are not static
     * to be statically called
     *
     * @param $name
     * @param $arguments
     *
     * @return DatabaseModelOLD|Fluent|null|void
     * @throws \Exception
     */
    public static function __callStatic($name, $arguments)
    {
        switch ($name) {
            case 'setDatabase':
                if (!$arguments[0] instanceof Driver) {
                    throw new Exception('Argument must be instance of Database Driver');
                }
                self::setDatabase($arguments[0]);
                break;
            case 'setContainer':
                if (!$arguments[0] instanceof ContainerInterface) {
                    throw new Exception('Argument must be instance of PSR Container');
                }
                self::setContainer($arguments[0]);
                break;
            default:
                throw new Exception('Method is not found');
        }
    }

    /**
     * Optional value for setters
     * e.g This method is used to indicate that the value of a property has changed
     * use this when you want to use getters and setters instead of properties it self
     * when getting or setting the properties the change is automatically recorded
     *
     * @param string $name     name of the property that will be changed
     * @param null   $bindName custom bind name
     */
    protected function hasChanged($name, $bindName = NULL)
    {
        if (!$this->lock) {
            $binding = is_null($bindName) ? $name : $bindName;
            $this->changed[$name] = ':' . $binding;
        }
    }

    /**
     * @inheritdoc
     *
     * @param $name
     * @param $arguments
     *
     * @return string
     * @throws \Dusan\PhpMvc\Database\Exceptions\PropertyNotFound
     */
    public function __call($name, $arguments)
    {
        if (strcmp($name, 'testInsert') === 0) {
            return $this->insert();
        } else if (strcmp($name, 'testUpdate') === 0) {
            return $this->update();
        } else if (preg_match("/^set([A-Z0-9]+[A-Za-z0-9]+)$/suD", $name, $matches) && count($arguments) === 1) {
            $newString = $this->snakeCase($matches[0]);
            $this->__set($newString, $arguments[0]);
        }
        return NULL;
    }

    /**
     * Transforms string from camelcase to snake case
     *
     * @param string $str
     *
     * @return false|mixed|string|string[]|null
     */
    private function snakeCase(string $str)
    {
        $newString = '';
        for ($i = 0; $i < mb_strlen($str); $i++) {
            if ($i === 0) {
                $newString = mb_strtolower($str[$i]);
                continue;
            }
            if (mb_ord($str[$i]) >= mb_ord('A') && mb_ord($str[$i]) <= mb_ord('Z')) {
                $newString .= '_' . mb_strtolower($str[$i]);
                continue;
            }
            $newString .= $str[$i];
        }
        return $newString;
    }

    /**
     * Override the default value of the table
     * Default value for the table name is __CLASS__ + 's'
     * Sometimes this approach is not good so override when the naming convention
     * is not valid eg. Library -> Librarys (should be Libraries)
     * String must be returned because this value is used in later processing by the framework
     *
     * @api
     * @return string
     */
    protected function setTable(): string
    {
        $exp = explode('\\', $this->getClass());
        return strtolower($exp[count($exp) - 1]) . 's';
    }

    /**
     * @return string|void
     */
    public function serialize()
    {
        return serialize($this->jsonSerialize());
    }

    /**
     * @param string $serialized
     */
    public function unserialize($serialized)
    {
        $unserialized = unserialize($serialized);
        foreach ($unserialized as $member => $value) {
            $this->{$member} = $value;
        }
    }

    protected static function setObserver(): ?string
    {
        return null;
    }



    /**
     * Method for saving the record to the database
     * On success in returns true adn on failure throws the error
     * It differs from it's counterpart save() method which returns false on failure
     *
     * @api
     * @throws \PDOException
     * @see DatabaseModelOLD::save()
     * @return void
     */
    public final function saveOrFail(): void
    {
        $insertStatement1 = static::$database->transaction(function (Driver $database) {
            $customBindings = false;
            $update = false;
            $insert = false;
            $bindings = [];
            $this->changed = array_unique($this->changed);
            if ($this->getId() !== NULL) {
                if (self::$observerInstance) {
                    self::$observerInstance->updating();
                }
                if ($this instanceof CustomUpdate) {
                    $database->sql($this->setUpdate());
                    $bindings = $this->setUpdateBindings();
                    $customBindings = true;
                } else {
                    $database->sql($this->update());
                }
                $update = true;
            } else {
                if (self::$observerInstance) {
                    self::$observerInstance->creating();
                }
                if ($this instanceof CustomInsert) {
                    $database->sql($this->setInsert());
                    $bindings = $this->setInsertBindings();
                    $customBindings = true;
                } else {
                    $database->sql($this->insert());
                }
                $insert = true;
            }
            $bind = [];
            if($customBindings) {
                $bind = $bindings;
            } else {
                if ($update) {
                    $bind = $this->changed;
                    $bind[$this->primaryKey] = ':' . $this->primaryKey;
                }

                if ($insert) {
                    foreach ($this->protected as $value) {
                        $bind[$value] = ':' . $value;
                    }
                }
            }

            foreach ($bind as $member => $binding) {
                $database->bindValue($binding, $this->__get($member), $this->memberTypeBindings[$member] ?? PDO::PARAM_STR);
            }
            $database->execute(NULL, true);
            if (self::$observerInstance !== NULL) {
                if ($insert) {
                    self::$observerInstance->created($this);
                } else if ($update) {
                    self::$observerInstance->updated($this);
                }
            }
            return $insert;
        });
        if ($insertStatement1) {
            $output = static::$database
                ->bindToClass($this->getClass())
                ->getLastInsertedRow($this->getTable(), $this->primaryKey);
            if (count($output) === 1) {
                $object = $output[0];
                array_splice($this->protected, array_search('id', $this->protected));
                $array = $this->diff(get_object_vars($object), $this->protected);
                foreach ($array as $item => $value) {
                    $this->lock(function () use ($item, $value) {
                        $this->__set($item, $value);
                    });
                }
            }
        }
    }

    /**
     * Method for saving record to database
     * On successful insert/update <b>true</b> is returned from this method and on failure <b>false</b> is returned
     *
     * @api
     * @see DatabaseModelOLD::saveOrFail()
     * @return bool
     */
    public final function save()
    {
        try {
            $this->saveOrFail();
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

}