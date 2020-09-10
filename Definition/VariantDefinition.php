<?php

namespace MutableTypedData\Definition;

/**
 * Defines a variant for mutable data.
 *
 * Each variant has:
 *  - a name
 *  - a label
 *  - a list of child properties
 *
 * The name and label may be used to construct options for the mutable data's
 * variant controlling property, unless it defines options itself.
 *
 * When a mutable data item's variant controlling property is set, the variant's
 * properties are added to the mutable data.
 */
class VariantDefinition {

  protected $label;

  protected $properties = [];

  static public function create() {
    return new static();
  }

  public function setLabel($label) {
    $this->label = $label;
    return $this;
  }

  public function getLabel() {
    return $this->label;
  }

  public function setProperties(array $properties) {
    $this->properties = $properties;

    // Fill in machine names as a convenience.
    foreach ($this->properties as $name => $property) {
      $property->setName($name);
    }

    return $this;
  }

  public function addProperties(array $properties): self {
    foreach ($properties as $name => $property) {
      // Fill in machine names as a convenience.
      $property->setName($name);

      $this->properties[$name] = $property;
    }

    return $this;
  }

  public function getProperties() {
    return $this->properties;
  }

}
