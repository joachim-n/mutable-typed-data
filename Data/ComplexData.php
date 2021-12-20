<?php

namespace MutableTypedData\Data;

use MutableTypedData\Definition\DataDefinition;
use MutableTypedData\Exception\InvalidAccessException;
use MutableTypedData\Exception\InvalidDefinitionException;
use MutableTypedData\Exception\InvalidInputException;

/**
 * Represents a data item that is composed of multiple properties.
 */
class ComplexData extends DataItem implements \IteratorAggregate {

  /**
   * The properties of this data.
   *
   * For this class, these are the same as the properties from the definition,
   * but subclasses may change this behaviour, hence the need to copy them to
   * this object.
   *
   * @var array
   */
  protected $properties;

  protected $value = [];

  public static function isSimple(): bool {
    return FALSE;
  }

  public function __construct(DataDefinition $definition) {
    parent::__construct($definition);

    $this->properties = $this->definition->getProperties();
  }

  /**
   * Returns the iterator for this object.
   *
   * iterates over data!!! auto-creates!
   */
  public function getIterator() {
    // Iterate in the order the properties are defined in, rather than the
    // keys in the $this->value array, which are in whichever order a value
    // was set.
    foreach ($this->properties as $name => $property) {
      if (!$this->revealInternal) {
        if ($property->isInternal()) {
          continue;
        }
      }

      if (!isset($this->value[$name])) {
        $this->value[$name] = $this->factoryClass::createFromDefinition($this->properties[$name], $this);
      }

      yield $name => $this->value[$name];
    }
  }

  public function items(): array {
    return $this->value;
  }

  // Hides internals unless requested.
  public function getProperties() {
    $return = [];

    // Use the local properties, as subclasses may change them.
    foreach ($this->properties as $name => $property) {
      if (!$this->revealInternal) {
        if ($property->isInternal()) {
          continue;
        }
      }

      $return[$name] = $property;
    }

    return $return;
  }

  // Hides internals unless requested.
  public function getPropertyNames() {
    $property_names = [];

    // Use the local properties, as subclasses may change them.
    foreach ($this->properties as $name => $property) {
      if (!$this->revealInternal) {
        if ($property->isInternal()) {
          continue;
        }
      }

      $property_names[] = $name;
    }

    return $property_names;
  }

  public function hasProperty($name) {
    return isset($this->properties[$name]);
  }

  /**
   * Gets a property definition.
   *
   * This is defined here rather than allowing the call to pass through to the
   * definition, because subclasses may change their properties dynamically.
   *
   * @param string $name
   *   The property name.
   *
   * @return \MutableTypedData\Definition\DataDefinition
   *   The definition.
   *
   * @throws \MutableTypedData\Exception\InvalidAccessException
   *   Throws an exception if the property does not exist, or if it is internal
   *   and the data is not set to show internals.
   */
  public function getProperty(string $name): DataDefinition {
    if (!$this->hasProperty($name)) {
      throw new InvalidAccessException(sprintf("Attempt to get definition for nonexistent property '%s' at %s.",
        $name,
        $this->getAddress()
      ));
    }

    $property = $this->properties[$name];

    if (!$this->revealInternal && $property->isInternal()) {
      throw new InvalidAccessException(sprintf("Attempt to get internal property '%s' at %s.",
        $name,
        $this->getAddress()
      ));
    }

    return $this->properties[$name];
  }

  public function __set($name, $value) {
    if (!array_key_exists($name, $this->properties)) {
      throw new InvalidAccessException(sprintf("Attempt to set nonexistent property '%s' at %s.",
        $name,
        $this->getAddress()
      ));
    }

    // TODO: hand over to set()!

    if (!isset($this->value[$name])) {
      // yeah this works for a string, but what if it's a component????
      $item_data = $this->factoryClass::createFromDefinition($this->properties[$name], $this);
      $this->value[$name] = $item_data;
    }

    $this->value[$name]->set($value);
    $this->set = TRUE;

    // Don't bubble up, as the child data getting set will do that.
  }

  /**
   * Sets the whole or partial value of the data.
   *
   * Note that this doesn't clobber existing values not contained in the given
   * array.
   *
   * @param array $value
   *   The values to set. Keys should be property names.
   */
  public function set($value) {
    if (!is_array($value)) {
      throw new InvalidInputException(sprintf(
        "Attempt to set a non-array value on complex data at address %s. Set individual values with properties.",
        $this->getAddress()
      ));
    }

    foreach ($value as $name => $item_value) {
      if (!array_key_exists($name, $this->properties)) {
        throw new InvalidAccessException(sprintf(
          "Attempt to set nonexistent property '%s' at %s with value '%s', available properties are: %s.",
          $name,
          $this->getAddress(),
          print_r($item_value, TRUE),
          implode(', ', $this->definition->getPropertyNames())
        ));
      }

      if (!isset($this->value[$name])) {
        $item_data = $this->factoryClass::createFromDefinition($this->properties[$name], $this);
        $this->value[$name] = $item_data;
      }

      $this->value[$name]->set($item_value);
    }
    $this->set = TRUE;

    // Don't bubble up, as the child data getting set will do that.
  }

  public function removeItem(string $property_name) {
    unset($this->value[$property_name]);
  }

  public function validate(): array {
    $violations = parent::validate();

    // Check properties, and ensure that those which are required are
    // instantiated so they can be validated.
    foreach ($this->properties as $name => $definition) {
      // Don't validate internals unless they are revealed.
      if (!$this->revealInternal) {
        if ($definition->isInternal()) {
          continue;
        }
      }

      if ($definition->isRequired()) {
        if ($definition->isMultiple()) {
          throw new InvalidDefinitionException(sprintf("Required multiple-valued data is not supported, address was %s.",
            $this->{$name}->getAddress()
          ));
        }

        // Need to explicitly get the property in case $name happens to be also
        // the name of one of our internal properties, which then means the
        // magic __get() wouldn't be called because here the property is
        // accessible.
        $this->get($name)->value;
      }
    }
    // dsm($this->value);

    foreach ($this->value as $item) {
      $violations = array_merge($violations, $item->validate());
    }

    return $violations;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    $values_are_empty = [];

    // Only consider visible properties.
    foreach ($this->getProperties() as $name => $property_definition) {
      // Avoid instantiating child data.
      $values_are_empty[] = !isset($this->value[$name]) || $this->value[$name]->isEmpty();
    }

    return (bool) array_product($values_are_empty);
  }

  public function import($value) {
    // Always treat this as set, even if the incoming value is an empty array.
    $this->set = TRUE;

    foreach ($value as $name => $item_value) {
      if (array_key_exists($name, $this->properties)) {
        if (!isset($this->value[$name])) {
          $item_data = $this->factoryClass::createFromDefinition($this->properties[$name], $this);
          $this->value[$name] = $item_data;
        }

        $this->value[$name]->import($item_value);
      }
    }
  }


  public function __isset($name) {
    // Need to implement this so empty() works on $data_item->value.
    $return = isset($this->value[$name]);
    return $return;
  }

  public function __get($name) {
    return $this->get($name);
  }

  public function get(string $name = '') {
    if (empty($name)) {
      throw new InvalidAccessException(sprintf(
        "Accessing complex data at %s without a property name.",
        $this->getAddress()
      ));
    }

    if (!array_key_exists($name, $this->properties)) {
      throw new InvalidAccessException(sprintf(
        "Unknown property '%s' on data at '%s', available properties are: %s.",
        $name,
        $this->getAddress(),
        implode(', ', $this->definition->getPropertyNames())
      ));
    }

    // dump($this->value);
    // For complex data, accessing the property should auto-create it??
    // this is to allow chaining like $data->complex->subProperty = 'value'
    if (!isset($this->value[$name])) {
      $item_data = $this->factoryClass::createFromDefinition($this->properties[$name], $this);
      $this->value[$name] = $item_data;
    }


    // if (empty($this->generatorClass)) {
    //   throw new \Exception("type must be set first");
    // }

    // assert(isset($this->value[$name]));
    // dump($this->value[$name]);
    // dump($this->value[$name]->value);
    // ????
    // dump($this->value);

    return $this->value[$name];
  }

  public function access(): void {
    // Access each child property.
    // Iterating over ourselves takes care of whether we're set to reveal
    // internal or not.
    foreach ($this as $item) {
      $item->access();
    }
  }


  public function walk(callable $callback) {
    $callback($this);

    foreach ($this->value as $item) {
      $item->walk($callback);
    }
  }

  protected function restoreOnWake() {
    $this->properties = $this->definition->getProperties();

    foreach ($this->value as $name => $value) {
      $value->parent = $this;
      $value->definition = $this->definition->getProperty($name);

      $value->restoreOnWake();
    }
  }


  protected function getRaw() {
    $export = [];
    foreach ($this->value as $name => $value) {
      $export[$name] = $value->getRaw();
    }

    return $export;
  }

  public function export() {
    $export = [];
    foreach ($this->value as $name => $value) {
      $export[$name] = $value->export();
    }

    return $export;
  }

}
