<?php

namespace MutableTypedData\Data;

use MutableTypedData\Definition\DataDefinition;
use MutableTypedData\Definition\VariantDefinition;
use MutableTypedData\Exception\InvalidAccessException;
use MutableTypedData\Exception\InvalidDefinitionException;
use MutableTypedData\Exception\InvalidInputException;

/**
 * Represents a data item whose properties can change according to a type.
 *
 * This must be defined with a single property with options, and further
 * properties defined with variants. The value of the single property (the
 * 'variant property') must be set before any further properties are set.
 *
 * The options for the variant property either correspond exactly to variant
 * names, or a mapping must be defined.
 *
 * @see \MutableTypedData\Definition\VariantDefinition
 */
class MutableData extends ComplexData {

  /**
   * The variant type of the data.
   *
   * Needs to be protected so that $data->variant accesses the data object via
   * the magic __get().
   *
   * @var string
   */
  protected $variant;

  /**
   * The name of the property that holds the type.
   *
   * TODO: name to variant.
   *
   * @var string
   */
  protected $typePropertyName;

  const SERIALIZABLE = [
    'value',
    'set',
    'name',
    'delta',
    'definitionSource',
    'factoryClass',
    'variant',
    'typePropertyName',
  ];

  // set once type is set.
  protected $properties;

  public function __construct(DataDefinition $definition) {
    parent::__construct($definition);

    if (count($this->properties) != 1) {
      throw new InvalidDefinitionException(sprintf("Mutable data at %s must have exactly one property.",
        $this->getAddress()
      ));
    }

    // Determine the name of the type property.
    $this->typePropertyName = array_keys($this->properties)[0];

    $types = $definition->getVariants();
    if (empty($types)) {
      throw new InvalidDefinitionException(sprintf("Type property '%s' on data item '%s' has no variants set.",
        $this->typePropertyName,
        $this->getAddress() ?: 'root'
      ));
    }

    // Set the options based on the type
    $type_property = $this->properties[$this->typePropertyName];

    if (!$type_property->hasOptions()) {
      // $types
    }
  }

  public function getVariantDefinition(): VariantDefinition {
    if (empty($this->variant)) {
      throw new \Exception(sprintf(
        "Variant must be set before calling getVariantDefinition() at %s.",
        $this->getAddress()
      ));
    }

    return $this->definition->getVariants()[$this->variant];
  }

  public function __set($name, $value) {
    if ($name != $this->typePropertyName && empty($this->variant)) {
      throw new InvalidInputException(sprintf("Variant must be set before any other property; attempt to set %s on %s.",
        $name,
        $this->getAddress()
      ));
    }

    if ($name == $this->typePropertyName && empty($value)) {
      throw new InvalidInputException(sprintf(
        "Mutable data at %s may not have its variant property '%s' set to an empty value.",
        $this->getAddress(),
        $this->typePropertyName
      ));
    }

    // Note that we don't need to call setVariant() here; that's done when the
    // variant property's value bubbles up in onChange().
    parent::__set($name, $value);
  }

  public function set($value) {
    // Same work as the parent class, so it can be checked before we try
    // accessing $value as an array.
    if (!is_array($value)) {
      throw new InvalidInputException(sprintf(
        "Attempt to set a non-array value on mutable data at address %s. Set individual values with properties.",
        $this->getAddress()
      ));
    }

    // Ensure the variant property is the first in the array if present, as if
    // it contains the variant type but it's not first in the array, the call to
    // the parent will fail because the properties are not yet defined.
    if (!empty($value[$this->typePropertyName]) && count($value) > 1 && array_key_first($value) != $this->typePropertyName) {
      $first_value_array = [
        $this->typePropertyName => $value[$this->typePropertyName],
      ];
      // Array union ignores later repeated keys, so the variant property in
      // its original position is ignored in favour of the one we put at the
      // front.
      $value = $first_value_array + $value;
    }

    if (empty($this->variant) && empty($value[$this->typePropertyName])) {
      throw new InvalidInputException(sprintf(
        "Variant property %s must be set before any other property; attempt to set properties %s on %s.",
        $this->typePropertyName,
        implode(', ', array_keys($value)),
        $this->getAddress()
      ));
    }

    parent::set($value);
  }

  public function onChange(DataItem $data_item, $value) {
    // Set the type when the value is set on our type property.
    // This is the only place we need to react; we don't need to override
    // __set(), as ComplexData will take care of creating the variant property
    // data item and setting the value on it, which then bubbles up to us here.
    // We allow the variant to be changed after it's previously been set. It's
    // up to UIs to warn users that this will lose data in variant properties.
    // TODO: possible bug here if the variant property's machine name is also
    // used somewhere else in the data, for instance, if a variant property is
    // complex and has a child property with the same name!!!
    if ($data_item->getAddress() == $this->getVariantData()->getAddress()) {
      $this->setVariant($value);
    }
  }


  public function getIterator() {
    if (!isset($this->value[$this->typePropertyName])) {
      $this->value[$this->typePropertyName] = $this->factoryClass::createFromDefinition($this->properties[$this->typePropertyName], $this);
    }
    yield $this->typePropertyName => $this->value[$this->typePropertyName];

    if (!isset($this->variant)) {
      return;
    }

    $properties_copy = $this->properties;
    // Remove the type.
    array_shift($properties_copy);

    foreach ($properties_copy as $name => $property) {
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

  public function getVariantData(): DataItem {
    return $this->get($this->typePropertyName);
  }

  public function isEmpty(): bool {
    // Allow the type property value to either be non-existent, or instantiated
    // and empty itself.
    return empty($this->value[$this->typePropertyName]) || $this->value[$this->typePropertyName]->isEmpty();
  }

  protected function setVariant($variant_property_value, $skip_mapping = FALSE) {
    if (empty($variant_property_value)) {
      throw new \Exception(sprintf(
        "Mutable data at %s may not have its variant property '%s' set to an empty value.",
        $this->getAddress(),
        $this->typePropertyName
      ));
    }

    // if (!empty($this->variant)) {
    //   throw new \Exception("Cannot set variant again.");
    // }

    // If restoring on wake, we don't need the variant mapping as we're setting
    // the actual value.
    // TODO: needs tests!
    if (!$skip_mapping && $mapping = $this->definition->getVariantMapping()) {
      if (!isset($mapping[$variant_property_value])) {
        throw new InvalidInputException(sprintf("Invalid variant '%s' at address %s.",
          $variant_property_value,
          $this->getAddress()
        ));
      }

      $this->variant = $mapping[$variant_property_value];
    }
    else {
      $this->variant = $variant_property_value;
    }

    // Allow changing the variant: zap any properties other than the variant
    // property.
    $this->properties = array_intersect_key($this->properties, [$this->typePropertyName => TRUE]);

    // Merge the properties for the type into the existing ones, which so far
    // will only contain the 'type' property.
    $this->properties += $this->definition->getVariantProperties($this->variant);

    // Remove any values that are not for the new variant.
    $this->value = array_intersect_key($this->value, $this->properties);
  }

  protected function restoreOnWake() {
    $this->properties = $this->definition->getProperties();

    // Restore properties from the variant.
    if (isset($this->variant)) {
      $this->setVariant($this->variant, TRUE);
    }
    // dump(array_keys($this->properties));

    foreach ($this->value as $name => $value) {
      $value->parent = $this;
      $value->definition = $this->properties[$name];

      $value->restoreOnWake();
    }
  }


}