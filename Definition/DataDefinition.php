<?php

namespace MutableTypedData\Definition;

use MutableTypedData\Exception\InvalidDefinitionException;

/**
 * Defines a data property.
 */
class DataDefinition {

  /**
   * The type of data the property holds.
   *
   * @var string
   *   One the types defined in \MutableTypedData\DataItemFactory::$types,
   *   or a custom type defined in a child class.
   */
  protected $type;

  /**
   * The machine name of this property.
   *
   * @var string|null
   */
  protected $name = NULL;

  /**
   * The human-readable name of this property.
   *
   * This is shown in UIs to the user.
   *
   * @var string
   */
  protected $label = '';

  /**
   * The description of this property.
   *
   * This is shown in UIs in addition to the label to provide extra detail.
   *
   * @var string
   */
  protected $description = '';

  /**
   * Whether this property is required.
   *
   * @var bool
   */
  protected $required = FALSE;

  /**
   * Whether this property is internal, and thus not shown in UIs.
   *
   * @var bool
   */
  protected $internal = FALSE;

  /**
   * The default value definition for this property.
   *
   * @var \MutableTypedData\Definition\DefaultDefinition
   */
  protected $default = NULL;

  protected $variants = [];

  protected $variantMapping = NULL;

  protected $isVariantProperty = FALSE;

  /**
   * The child properties of this data.
   *
   * This is not set if the data is simple.
   *
   * @var self[]
   */
  protected $properties = [];

  /**
   * The number of values this data can have.
   *
   * A value of -1 represents unlimited cardinality.
   *
   * @var int
   */
  protected $cardinality = 1;

  protected $options = NULL;

  protected $validators = [];

  /**
   * Constructor.
   *
   * @param string $type
   *   The data type of this definition.
   */
  public function __construct($type) {
    $this->type = $type;
  }

  /**
   * Factory method.
   *
   * Use instead of 'new' for a fluent syntax.
   *
   * @param string $type
   *  The data type of this definition.
   *
   * @return static
   *   The new definition obkect.
   */
  static public function create($type): self {
    return new static($type);
  }

  /**
   * Clones this definition for use with a delta item.
   *
   * @return static
   *   The new definition.
   */
  public function getDeltaDefinition(): self {
    $delta_definition = clone $this;

    // Remove the multiple cardinality.
    $delta_definition->setMultiple(FALSE);

    // Remove the default.
    $delta_definition->default = NULL;

    return $delta_definition;
  }

  public function getType(): string {
    return $this->type;
  }

  public function setInternal(bool $internal): self {
    $this->internal = $internal;

    return $this;
  }

  public function isInternal(): bool {
    return $this->internal;
  }

  /**
   * Sets a default definition.
   *
   * See also the shorthand methods for setting a simple default.
   *
   * @param DefaultDefinition $default
   *   The default definition.
   *
   * @return static
   */
  public function setDefault(DefaultDefinition $default): self {
    $this->default = $default;
    return $this;
  }

  public function setLiteralDefault($default_value): self {
    $this->setDefault(DefaultDefinition::create()->setLiteral($default_value));
    return $this;
  }

  public function setExpressionDefault(string $default_value): self {
    $this->setDefault(DefaultDefinition::create()->setExpression($default_value));
    return $this;
  }

  public function setCallableDefault(callable $default_value): self {
    $this->setDefault(DefaultDefinition::create()->setCallable($default_value));
    return $this;
  }

  public function getDefault(): ?DefaultDefinition {
    return $this->default;
  }

  /**
   * Defines the variants for mutable data.
   *
   * @param array $variants
   *   An array of VariantDefinition objects.
   *
   * @return static
   */
  public function setVariants(array $variants): self {
    if ($this->type != 'mutable') {
      throw new InvalidDefinitionException("Only mutable data can have types.");
    }

    // Not ideal, but we need the type property available to set options on it.
    if (empty($this->properties)) {
      throw new InvalidDefinitionException("Must call setProperties() before setVariants().");
    }

    // Fill in machine names as a convenience.
    foreach ($variants as $variant_name => $variant) {
      $properties = $variant->getProperties();
      foreach ($properties as $name => $property) {
        $property->setName($name);
      }
    }

    $this->variants = $variants;

    // Fill in the options on the type property if not defined.
    $type_property = reset($this->properties);
    // If the property defines its own options and the call to setOptions()
    // is made later, it will override what we do here.
    if (!$type_property->hasOptions()) {
      $options = [];

      if ($this->hasVariantMapping()) {
        $options = $this->getVariantMapping();
      }
      else {
        foreach ($variants as $variant_name => $variant) {
          $variant_label = $variant->getLabel();

          if (empty($variant_label)) {
            // TODO: needs test.
            throw new InvalidDefinitionException("Variants on data that does not define options must have a label.");
          }

          $options[$variant_name] = $variant->getLabel();
        }
      }

      // TODO: convert this to setOptions().
      $type_property->setOptionsArray($options);
    }

    return $this;
  }

  public function setAsVariantProperty() {
    $this->isVariantProperty = TRUE;
  }

  public function getVariants() {
    return $this->variants;
  }

  public function isVariantProperty(): bool {
    return $this->isVariantProperty;
  }

  // TODO: change to return the def object?
  public function getVariantProperties(string $type) {
    return $this->variants[$type]->getProperties();
  }

  public function setProperties(array $properties): self {
    // TODO! this won't catch child classes of SimpleData!!!
    // TODO we don't have access to the factory here. So we can't ask the
    // factory about its types!!
    if ($this->type == 'string' || $this->type == 'boolean') {
      throw new InvalidDefinitionException("Simple data can't have sub-properties.");
    }

    if ($this->type == 'mutable') {
      if (count($properties) != 1) {
        throw new InvalidDefinitionException("Mutable data must have only the type property.");
      }

      $variant_property = reset($properties);

      // Ensure it's required as a convenience.
      $variant_property->setRequired(TRUE);

      $variant_property->setAsVariantProperty();
    }

    $this->properties = $properties;

    // Fill in machine names as a convenience.
    foreach ($this->properties as $name => $property) {
      $property->setName($name);
    }

    return $this;
  }

  public function getProperties() {
    return $this->properties;
  }

  public function getPropertyNames() {
    return array_keys($this->properties);
  }

  /**
   * Gets a child property definition.
   *
   * @param string $name
   *  The property name.
   *
   * @return static
   *  The definition.
   *
   * @throws \Exception
   *  Throws an exception if the property doesn't exit.
   */
  public function getProperty(string $name): self {
    if (!isset($this->properties[$name])) {
      throw new \Exception(sprintf("Property definition '%s' has no child property '$name' defined.",
        $this->name,
        $name
      ));
    }

    return $this->properties[$name];
  }

  /**
   * Gets a property definition from anywhere in the structure.
   *
   * This may only be called on the root property definition.
   *
   * @param string $address
   *  The property address. This must be absolute, as definitions aren't aware
   *  of their parent.
   *
   * @return static
   *
   * @throws \Exception
   *  Throws an exception if the property doesn't exit.
   */
  public function getNestedProperty($address): self {
    $property_definition = $this;

    $address_pieces = explode(':', $address);

    // Remove the root address piece.
    array_shift($address_pieces);

    foreach ($address_pieces as $child_name) {
      // Skip over a delta address piece.
      if (is_numeric($child_name)) {
        continue;
      }

      if (isset($property_definition->properties[$child_name])) {
        $property_definition = $property_definition->properties[$child_name];
        continue;
      }

      // If the definition doesn't have the property, check the variants.
      if ($property_definition->variants) {
        foreach ($property_definition->variants as $variant_definition) {
          $variant_properties = $variant_definition->getProperties();
          if (isset($variant_properties[$child_name])) {
            $property_definition = $variant_properties[$child_name];
            continue 2;
          }
        }
      }

      throw new \Exception("Unable to find property $child_name in property address $address.");
    }

    return $property_definition;
  }

  public function addProperty(self $property): self {
    // TODO! this won't catch child classes of SimpleData!!!
    if ($this->type == 'string' || $this->type == 'boolean') {
      // TODO: needs tests
      throw new InvalidDefinitionException("Simple data can't have sub-properties.");
    }

    if ($this->type == 'mutable') {
      throw new InvalidDefinitionException("Mutable data must have only the type property.");
    }

    if (empty($property->getName())) {
      throw new InvalidDefinitionException("Properties added with addProperty() must have a machine name set.");
    }

    $this->properties[$property->getName()] = $property;

    return $this;
  }

  /**
   * Adds properties to this definition.
   *
   * @param array $properties
   *  An array of data definitions. These are appended to existing properties.
   *  If any keys in this array correspond to existing properties, the existing
   *  definition is overwritten. The replacement property will be in the order
   *  given in the $properties array, not in its original position.
   *
   * @return static
   */
  public function addProperties(array $properties): self {
    if ($this->type == 'string') {
      throw new InvalidDefinitionException("String data can't have sub-properties.");
    }

    if ($this->type == 'mutable') {
      throw new InvalidDefinitionException("Mutable data must have only the type property.");
    }

    foreach ($properties as $name => $property) {
      // Fill in machine names as a convenience.
      $property->setName($name);

      // Explicitly unset an existing property, so that the incoming order is
      // used.
      if (isset($this->properties[$name])) {
        unset($this->properties[$name]);
      }

      $this->properties[$name] = $property;
    }

    return $this;
  }

  public function removeProperty(string $name) {
    unset($this->properties[$name]);

    return $this;
  }

  /**
   * Sets the name for the data.
   *
   * The name is a machine identifier for the data, which is used by code but
   * not seen by users.
   *
   * The name of data is used:
   *  - To form the address within the structure.
   *  - To use values on child properties with set() and get().
   *  - For magic properties, for example, $data->NAME->value.
   *
   * This is set automatically if not set with this method:
   *  - The root data's name is set to 'data'.
   *  - Child properties get their names set to the keys of the array of
   *    definitions given to setProperties().
   *  - Delta items get their name set to their delta.
   *
   * In practice, only the root definition should have the default overridden.
   *
   * @param string $name
   *   The name. This may not contain a ':', as that is used as the address
   *   separator. Addiotionally, to use the magic properties on complex data
   *   the name should only contain characters that are valid in a PHP property
   *   name.
   *
   * @return static
   */
  public function setName($name): self {
    if (!empty($this->name) && $this->name != $name) {
      throw new InvalidDefinitionException("Machine name of property {$this->name} can't be changed to {$name}.");
    }

    if (strpos($name, ':') !== FALSE) {
      throw new InvalidDefinitionException("Machine name '{$name}' may not contain a ':'.");
    }

    $this->name = $name;

    return $this;
  }

  /**
   * @deprecated
   *
   * Use self::setName().
   */
  public function setMachineName($name): self {
    return $this->setName($name);
  }

  public function getName(): ?string {
    return $this->name;
  }

  /**
   * Sets the label for the data.
   *
   * The label is used by UIs to identify the data to the user.
   *
   * @param string $label
   *   The label. This may contain any characters.
   *
   * @return static
   */
  public function setLabel($label): self {
    $this->label = $label;
    return $this;
  }

  public function getLabel() {
    if (empty($this->label)) {
      trigger_error("Property for $this->name is missing a label.", E_USER_ERROR);
    }

    return $this->label;
  }

  /**
   * Sets the description for the data.
   *
   * The description is used by UIs to provide additional instructions or
   * explanations to the user.
   *
   * @param string $description
   *   The description. This may contain any characters.
   *
   * @return static
   */
  public function setDescription($description): self {
    $this->description = $description;
    return $this;
  }

  public function getDescription() {
    return $this->description;
  }

  public function setMultiple(bool $multiple): self {
    if ($multiple) {
      $this->cardinality = -1;
    }
    else {
      $this->cardinality = 1;
    }
    return $this;
  }

  public function setMaxCardinality(int $cardinality): self {
    $this->cardinality = $cardinality;

    return $this;
  }

  public function isMultiple(): bool {
    return ($this->cardinality != 1);
  }

  public function getCardinality(): int {
    return $this->cardinality;
  }

  public function isComplex(): bool {
    return (count($this->properties) > 0);
  }

  public function isMutable(): bool {
    return !empty($this->variants);
  }

  public function setRequired($required): self {
    $this->required = $required;
    return $this;
  }

  public function isRequired() {
    return $this->required;
  }

  /**
   * Sets the list of valid values for the data this defines.
   *
   * @param OptionDefinition ...$options
   *  One or more option definitions.
   *
   * @return static
   */
  public function setOptions(OptionDefinition ...$options): self {
    $this->options = [];
    foreach ($options as $option) {
      $this->options[$option->getValue()] = $option;
    }
    return $this;
  }

  /**
   * Adds a single option to the data this defines.
   *
   * @param OptionDefinition $option
   *  A single option definition.
   *
   * @return static
   */
  public function addOption(OptionDefinition $option): self {
    $this->options[$option->getValue()] = $option;
    return $this;
  }

  /**
   * Sets the options as an array of values and labels.
   *
   * This is more convenient to call than setOptions() when the options have no
   * descriptions.
   *
   * @param array $options_array
   *   An array of options, where:
   *    - Array keys are the option values, that is, the data to store.
   *    - Array values are labels, that is, what UIs should show to the user.
   *
   * @return static
   */
  public function setOptionsArray(array $options_array): self {
    $this->options = [];
    foreach ($options_array as $value => $label) {
      $this->addOption(OptionDefinition::create($value, $label));
    }
    return $this;
  }

  public function hasOptions(): bool {
    return !empty($this->options);
  }

  public function getOptions(): array {
    return $this->options ?? [];
  }

  public function setValidators(string ...$validators): self {
    $this->validators = $validators;
    return $this;
  }

  public function getValidators(): array {
    return $this->validators;
  }

  /**
   * Sets a mapping of data values to variant names.
   *
   * This allows the property on mutable data which sets the variant to have
   * values that don't match the names of variants.
   *
   * One use case is data which has more possible values than there are variants
   * and so where several values all map to the same variant. For example:
   *   - cat => mammal
   *   - dog => mammal
   *   - eagle => bird
   *   - robin => bird
   *
   * @param array $mapping
   *   The mapping array. Keys are option values on this definition; values
   *   are names of variants as set with setVariants().
   *
   * @return static
   */
  public function setVariantMapping(array $mapping): self {
    $this->variantMapping = $mapping;
    return $this;
  }

  public function hasVariantMapping(): bool {
    return !is_null($this->variantMapping);
  }

  public function getVariantMapping(): ?array {
    return $this->variantMapping;
  }

}
