<?php

namespace MutableTypedData\Definition;

/**
 * Interface for definitions which hold lists of properties.
 */
interface PropertyListInterface {

  /**
   * Sets the properties for this definition.
   *
   * Previously-defined properties are removed.
   *
   * @param array $properties
   *   An array of data definitions, keyed by the property name.
   *
   * @return self
   */
  public function setProperties(array $properties): self;

  /**
   * Adds a property to this definition.
   *
   * @param DataDefinition $property
   *   The property to add. It must have its name set already.
   *
   * @return self
   */
  public function addProperty(DataDefinition $property): self;

  /**
   * Adds properties to this definition.
   *
   * @param array $properties
   *  An array of data definitions. These are appended to existing properties.
   *  If any keys in this array correspond to existing properties, the existing
   *  definition is overwritten. The replacement property will be in the order
   *  given in the $properties array, not in its original position.
   *
   * @return self
   */
  public function addProperties(array $properties): self;

  /**
   * Removes a property.
   *
   * @param string $name
   *   The name of the property to remove.
   *
   * @return self
   *
   * TODO: Add return type in 2.0.0
   */
  public function removeProperty(string $name);

  /**
   * Gets a child property definition.
   *
   * @param string $name
   *  The property name.
   *
   * @return \MutableTypedData\Definition\DataDefinition
   *  The definition.
   *
   * @throws \Exception
   *  Throws an exception if the property doesn't exist.
   */
  public function getProperty(string $name): DataDefinition;

  /**
   * Gets this definition's properties.
   *
   * @return \MutableTypedData\Definition\DataDefinition[]
   *  An array of property definitions, keyed by the property name.
   *
   * TODO: Add return type in 2.0.0
   */
  public function getProperties();

  /**
   * Gets the names of this definition's properties.
   *
   * @return string[]
   *  An array of property names.
   *
   * TODO: Add return type in 2.0.0
   */
  public function getPropertyNames();

}