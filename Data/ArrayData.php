<?php

namespace MutableTypedData\Data;

use MutableTypedData\Exception\InvalidAccessException;
use MutableTypedData\Exception\InvalidInputException;

/**
 * Represents multi-valued data.
 *
 * Data values are accessed as numeric array keys:
 *   $data[0]
 *  and can be iterated over:
 *   foreach ($data as $delta => $item)
 *
 * Items can be created with array append notation:
 *   $data[] = 'new value';
 * or at specific deltas providing these are existing, or the next available
 * one:
 *   $data[0] = 'value';
 *   $data[1] = 'value';
 *   $data[1] = 'replace value';
 *   $data[3] = 'bad'; // Exception.
 */
class ArrayData extends DataItem implements \IteratorAggregate, \ArrayAccess, \Countable {

  /**
   * {@inheritdoc}
   */
  protected $value = [];

  /**
   * {@inheritdoc}
   */
  public static function isSimple(): bool {
    // Doesn't really matter; nothing should call this method on this class.
    return FALSE;
  }

  /**
   * Returns the value.
   *
   * This should be used internally, to ensure the default is applied.
   *
   * @return array
   */
  protected function getValue(): array {
    $this->applyDefault();

    return $this->value;
  }

  /**
   * {@inheritdoc}
   */
  public function items(): array {
    return $this->getValue();
  }

  /**
   * Returns the iterator for this object.
   */
  public function getIterator(): \Traversable {
    // return new \ArrayIterator($this->value);

    // NOT READY YET!
    foreach ($this->getValue() as $delta_item) {
      yield $delta_item;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function offsetExists(mixed $offset): bool {
    $value = $this->getValue();

    return isset($value[$offset]);
  }

  /**
   * {@inheritdoc}
   */
  public function offsetGet(mixed $offset): mixed {
    // Allow use of empty offset notation to append items.
    if (is_null($offset)) {
      $next_delta = $this->count();
      $offset = $next_delta;
    }

    if (!is_numeric($offset)) {
      throw new InvalidAccessException("ArrayData may only have numeric offsets.");
    }

    // Allow a default to be applied first.
    $this->getValue();

    // Allow autovivification of next offset, so we can do things like
    // $data->foo[0]->bar = 'value'
    $next_delta = $this->count();
    if ($offset == $next_delta) {
      $new_item = $this->createItem();
    }

    if (!isset($this->value[$offset])) {
      throw new InvalidAccessException("Offset $offset not found.");
    }

    return $this->value[$offset];
  }

  /**
   * {@inheritdoc}
   */
  public function offsetSet(mixed $offset, mixed $value): void {
    $next_delta = $this->count();

    // If the offset is NULL, the array access was the empty index operator, and
    // this is an autovivication of the next delta.
    if (is_null($offset)) {
      $offset = $next_delta;
    }

    // Now we've allowed for NULL offset, reject anything non-numeric.
    if (!is_numeric($offset)) {
      throw new InvalidAccessException("ArrayData may only have numeric offsets.");
    }

    // Create a new item if this is the next delta (or if it was an empty index
    // operator, handled above).
    if ($offset == $next_delta) {

    // TODO! doesn't check mayAddItem()!


      $new_item = $this->createItem();
      $new_item->set($value);

      return;
    }

    // Now we've allowed for the next delta, reject anything not set.
    if (!isset($this->value[$offset])) {
      throw new InvalidAccessException("Delta $offset is not set.");
    }

    $this->value[$offset]->set($value);
    $this->set = TRUE;
  }

  /**
   * Unsets an array item.
   *
   * This allows data items to be removed by using unset().
   *
   * @param $delta
   *   The delta to remove.
   */
  public function offsetUnset(mixed $delta): void {
    if (!isset($this->value[$delta])) {
      throw new InvalidAccessException("Attempt to remove nonexistent delta {$delta}.");
    }

    unset($this->value[$delta]);

    // Re-key the remaining values.
    // TODO: the deltas AND names need to be updated!
    $this->value = array_values($this->value);
  }

  /**
   * {@inheritdoc}
   */
  public function removeItem(string $property_name) {
    $this->offsetUnset($property_name);
  }

  /**
   * Determines whether a further delta item may be added.
   *
   * @return bool
   *  TRUE if the item may be added; FALSE if not.
   */
  public function mayAddItem() {
    if ($this->getCardinality() == -1 || $this->getCardinality() > $this->count()) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Creates an extra item in the array data.
   *
   * @param int $delta
   *   The delta to insert the new item before.
   *
   * @return \MutableTypedData\Data\DataItem
   *   The new data item that's been created.
   */
  public function insertBefore(int $delta): DataItem {
    $definition = $this->definition->getDeltaDefinition();

    $new_item = $this->factoryClass::createFromDefinition($definition, $this, $delta);

    // New item has to be in an array, as array_splice() seems to detect array
    // interface rather than actual array, and so treats our data item as an
    // array.
    array_splice($this->value, $delta, 0, [$new_item]);

    // Re-set the deltas of the items.
    // Using the fact that we're in a DataItem here, so we can access the
    // protected property of other objects of the same class...
    foreach ($this->value as $delta => $item) {
      $item->delta = $delta;
      $item->name = $delta;
    }

    return $new_item;
  }

  /**
   * Appends a new item to the data.
   *
   * @internal
   *
   * TODO: rename!
   */
  public function createItem() {
    // We want the factory to give us a single item, not the array all over again!
    $definition = $this->definition->getDeltaDefinition();

    // Set the machine name of the item to the delta.
    $count = count($this->value);

    if ($count == $this->definition->getCardinality()) {
      throw new InvalidInputException(sprintf("Unable to add an item to data '%s', already at maximum cardinality of %s.",
        $this->getAddress(),
        $count
      ));
    }

    $next_delta = $count;

    // TODO: add a separate, internal method to factory for child items.
    $item_data = $this->factoryClass::createFromDefinition($definition, $this, $next_delta);

    $this->value[] = $item_data;
    $this->set = TRUE;

    return $item_data;
  }

  /**
   * {@inheritdoc}
   */
  public function __set($name, $value) {
    throw new InvalidAccessException(sprintf("Attempt to set object property %s on array data at %s.",
      $name,
      $this->getAddress()
    ));
  }

  /**
   * Undocumented function
   *
   * THIS REPLACES ALL VALUES!
   *
   * @param [type] $value
   *
   * @return void
   */
  public function set($value) {
    if (is_array($value)) {
      // Allow setting of multiple deltas at once with an array.
      if (empty($value)) {
        // Handle setting an empty value; the range() won't work with it.
        $this->value = [];
        $this->set = TRUE;

        return;
      }

      if (array_keys($value) != range(0, count($value) - 1)) {
        throw new InvalidInputException(sprintf("An array set on ArrayData at %s must have sequential numeric keys, got: %s.",
          $this->getAddress(),
          implode(', ', array_keys($value))
        ));
      }

      // Remove all existing values.
      $this->value = [];

      foreach ($value as $index => $item_value) {
        // pointlesss, we just killed value!
        if (isset($this->value[$index])) {
          $this->value[$index]->set($item_value);
        }
        else {
          $item_data = $this->createItem();
          $item_data->set($item_value);
        }
      }
      $this->set = TRUE;
    }
    else {
      $item_data = $this->createItem();
      $item_data->set($value);
      $this->set = TRUE;
    }
  }

  /**
   * Adds one or more values to this data.
   *
   * @param mixed $value
   *   A value to add to this data.
   *   - If the value is a scalar, then a new delta item is created for it.
   *   - If the value is an array, then each item of the array is added as a
   *     new delta item.
   */
  public function add($value) {
    if (!is_array($value)) {
      $value = [$value];
    }

    foreach ($value as $index => $item_value) {
      $item_data = $this->createItem();
      $item_data->set($item_value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function import($value) {
    // Always treat this as set, even if the incoming value is an empty array.
    $this->set = TRUE;

    foreach ($value as $delta => $item_value) {
      if (isset($this->value[$delta])) {
        $this->value[$delta]->import($item_value);
      }
      else {
        // TODO: check delta is correct here?
        $item_data = $this->createItem();
        $item_data->import($item_value);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validate(): array {
    $violations = parent::validate();

    if ($this->isEmpty() && $this->definition->isRequired()) {
      $violations[$this->getAddress()][] = 'Required value is empty';
    }

    if (!empty($this->value)) {
      foreach ($this->value as $item) {
        $violations = array_merge($violations, $item->validate());
      }
    }

    return $violations;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    if (empty($this->value)) {
      return TRUE;
    }

    $values_are_empty = [];

    foreach ($this->value as $value) {
      $values_are_empty[] = $value->isEmpty();
    }

    return (bool) array_product($values_are_empty);
  }

  /**
   * {@inheritdoc}
   */
  public function count(): int {
    return count($this->getValue());
  }

  /**
   * Determines whether the given value is a delta item of this data.
   *
   * @param mixed $value
   *   The value to check for.
   *
   * @return bool
   *   TRUE if the value is found; FALSE if not.
   */
  public function hasValue($value) {
    foreach ($this->getValue() as $delta_item) {
      if ($delta_item->value == $value) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function __get($name) {
    throw new InvalidAccessException(sprintf("Array data cannot be accessed as an object at address '%s'; property was '%s'.",
      $this->getAddress(),
      $name
    ));
  }

  /**
   * {@inheritdoc}
   */
  public function walk(callable $callback) {
    $callback($this);

    foreach ($this->getValue() as $item) {
      $item->walk($callback);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getRaw() {
    $export = [];
    foreach ($this->getValue() as $delta => $value) {
      $export[$delta] = $value->getRaw();
    }

    return $export;
  }

  /**
   * Gets the plain values for multiple simple data.
   *
   * @return array
   *   The values.
   *
   * @throws InvalidAccessException
   *   Throws an exception if the deltas are not simple data.
   */
  public function values(): array {
    // TODO the default does not get applied! Should it?
    $delta_item_class = $this->factoryClass::getTypeClass($this->definition->getType());

    if (!$delta_item_class::isSimple()) {
      throw new InvalidAccessException("Only simple data can only have values() called on an array.");
    }

    $return = [];
    foreach ($this->getValue() as $delta_item) {
      $return[] = $delta_item->value;
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function export() {
    $export = [];
    foreach ($this->getValue() as $delta => $value) {
      $export[$delta] = $value->export();
    }

    return $export;
  }

  /**
   * {@inheritdoc}
   */
  protected function restoreOnWake() {
    foreach ($this->value as $delta => $value) {
      $value->parent = $this;
      $value->definition = $this->definition->getDeltaDefinition();

      $value->restoreOnWake();
    }
  }

}
