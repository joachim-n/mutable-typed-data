<?php

namespace MutableTypedData\Data;

use MutableTypedData\Definition\DataDefinition;
use MutableTypedData\Exception\InvalidAccessException;
use MutableTypedData\Exception\InvalidDefinitionChangeException;
use MutableTypedData\Exception\InvalidDefinitionException;
use MutableTypedData\Exception\InvalidInputException;

/**
 * Represents a data item that is composed of multiple properties.TODO
 */
class ProxiedComplexData extends ComplexData {

  /**
   * The name of the property that is proxied.
   *
   * @var string
   */
  protected $proxiedPropertyName;

  public function __construct(DataDefinition $definition) {
    parent::__construct($definition);
    // Determine the name of the type property.
    $this->proxiedPropertyName = array_keys($this->properties)[0];
  }

  public static function XisSimple(): bool {
    // changes once reveal internal.
    if ($this->revealInternal) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator(): \Traversable {
    return new \ArrayIterator([]);
  }

  /**
   * {@inheritdoc}
   */
  public function items(): array {
    return [$this];
  }

  // has multiple propeties
  // BUT behaves as if only one with things like iteration
  // OK to set and access the other properties

  /**
   * {@inheritdoc}
   */
  protected function setPropertyValue(string $property_name, mixed $value): void {
    dump($property_name);
    if ($property_name == 'value') {
      // We mean the proxied property, set that.
      $property_name = $this->proxiedPropertyName;
    }

    if ($this->revealInternal) {
    }
    else {
      if ($property_name != $this->proxiedPropertyName) {
        throw new InvalidInputException(sprintf("Only proxied property on data item '%s' can be set before internals are shown.",
          $this->getAddress() ?: 'root'
        ));
      }
    }

    parent::setPropertyValue($property_name, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function set($value) {
    if ($this->revealInternal) {
      // Once internals are revealed, behave like ordinary complex data.
      parent::set($value);
    }
    else {
      // With internals hidden, behave as single data and set the proxied
      // property.
      $this->setPropertyValue($this->proxiedPropertyName, $value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function get(string $name = '') {
    if ($this->revealInternal) {
      // Once internals are revealed, behave like ordinary complex data.
      return parent::get($name);
    }
    else {
      // With internals hidden, behave as single data and get from the proxied
      // property.

      return parent::get($this->proxiedPropertyName)->get($name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validate(): array {
    if (isset($this->value[$this->proxiedPropertyName])) {
      return $this->value[$this->proxiedPropertyName]->validate();
    }
    else {
      return [];
    }
  }

  // how does import/export work?

}

// make this its own class so that it's easier to do __call() and go through
// to the first property??
// but then we wrap ComplexData which seems weird