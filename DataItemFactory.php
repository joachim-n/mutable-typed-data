<?php

namespace MutableTypedData;

use MutableTypedData\Definition\DefinitionProviderInterface;
use MutableTypedData\Definition\DataDefinition;
use MutableTypedData\Data\DataItem;
use MutableTypedData\Data\StringData;
use MutableTypedData\Data\BooleanData;
use MutableTypedData\Data\ArrayData;
use MutableTypedData\Data\ComplexData;
use MutableTypedData\Data\MutableData;
use MutableTypedData\Exception\InvalidDefinitionException;
use MutableTypedData\ExpressionLanguage\DataAddressLanguageProvider;
use MutableTypedData\Validator\ValidatorInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Creates data item objects.
 *
 * This may be subclassed to customize various aspects:
 *  - To add custom data types, override $types.
 *  - To customise the class used for multiple data, override $multipleData.
 *  - To add Expression Language functions, override
 *    $expressionLanguageProviders.
 *  - To add validators, override $validators.
 *
 * Data items keep a reference to the factory class that created them, and so
 * all subsequent data item creation will use the same factory class for
 * consistency.
 */
class DataItemFactory {

  /**
   * Mapping of type machine names to classes.
   *
   * @var array
   */
  static protected $types = [
    'string' => StringData::class,
    'boolean' => BooleanData::class,
    'complex' => ComplexData::class,
    'mutable' => MutableData::class,
  ];

  /**
   * The class to use for multiple-valued data.
   *
   * @var string
   */
  static protected $multipleData = ArrayData::class;

  /**
   * The names of Expression Language function provider classes to use.
   *
   * Subclasses may override this to add their own, but must always include
   * the classes that are given here.
   *
   * @var string[]
   */
  static protected $expressionLanguageProviders = [
    DataAddressLanguageProvider::class,
  ];

  /**
   * Mapping of validator names to validator classes.
   *
   * Keys are identifiers to be used in definitions. Values are fully-qualified
   * class names.
   *
   * @var array
   */
  static protected $validators = [
  ];

  /**
   * The instantiated Expression Language.
   */
  static protected $expressionLanguage;

  /**
   * Gets the class for a data type.
   *
   * @param string $type_name
   *   The data type name, as defined in static::$types.
   *
   * @return string
   *   The class name.
   */
  public static function getTypeClass(string $type_name): string {
    return static::$types[$type_name];
  }

  /**
   * Creates a data object from a definition provider.
   *
   * @param \MutableTypedData\Definition\DefinitionProviderInterface|string $class_or_object
   *   Either the definition provider object, or its class name.
   *
   * @return \MutableTypedData\Data\DataItem
   *   The new data item.
   */
  public static function createFromProvider($class_or_object): DataItem {
    if (!is_a($class_or_object, DefinitionProviderInterface::class, TRUE)) {
      throw new \TypeError('Parameter to createFromProvider() must implement DefinitionProviderInterface.');
    }

    if (is_object($class_or_object)) {
      $provider_class = get_class($class_or_object);
    }
    else {
      $provider_class = $class_or_object;
    }

    $definition = $provider_class::getDefinition();

    $data = static::createFromDefinition($definition);

    // TODO: typehint for setDefinitionSource might be callable???
    $data->setDefinitionSource($provider_class . '::getDefinition');

    return $data;
  }

  /**
   * Creates a data object from a definition providing callback.
   *
   * @param callable $callback
   *   A callback which returns a definition.
   *
   * @return \MutableTypedData\Data\DataItem
   *   The new data item.
   */
  public static function createFromCallback(callable $callback): DataItem {
    $definition = $callback();

    $data = static::createFromDefinition($definition);

    $data->setDefinitionSource($callback);

    return $data;
  }

  /**
   * Creates a new data item from a definition.
   *
   * WARNING: this way of creating a data item may not be used to create the
   * root data item if the data item is going to be serialized. Use
   * createFromCallback() or createFromProvider() instead.
   *
   * @param \MutableTypedData\Definition\DataDefinition $definition
   *   A definition object.
   * @param \MutableTypedData\Data\DataItem $parent
   *   (optional) The parent data item if the item to be created is not a root.
   * @param int $delta
   *   (optional) The delta of the item to be created if it is part of a
   *   multiple data set.
   *
   * @return \MutableTypedData\Data\DataItem
   *   The new data item.
   */
  public static function createFromDefinition(DataDefinition $definition, DataItem $parent = NULL, int $delta = NULL): DataItem {
    // Ensure a machine name.
    if ($parent) {
      $machine_name = $definition->getName();

      // Allow an empty machine name if this is a delta.
      // TODO: this is inconsistent, as this only happens with a root that has
      // no name! See TODO on ArrayData::createItem().
      if (is_null($machine_name) && is_int($delta)) {
        $machine_name = $delta;
      }

      if (!is_string($machine_name) && !is_numeric($machine_name)) {
        throw new InvalidDefinitionException("Machine name must be a string or number.");
      }

      //  We allow a 0 machine name because the first delta in a multiple value
      //  will be 0.
      if (is_null($machine_name)) {
        throw new InvalidDefinitionException("Non-root properties must have a machine name.");
      }
    }

    if ($definition->isMultiple()) {
      $item = new static::$multipleData($definition);
    }
    else {
      if (!isset(static::$types[$definition->getType()])) {
        throw new InvalidDefinitionException(sprintf("Unknown data type '%s' at '%s'.",
          $definition->getType(),
          $definition->getName()
        ));
      }

      $class = static::$types[$definition->getType()];

      $item = new $class($definition);
    }

    if ($parent) {
      $item->setParent($parent, $delta);
    }

    $item->setFactory(static::class);

    return $item;
  }

  /**
   * Gets the Expresssion Language object.
   *
   * @return \Symfony\Component\ExpressionLanguage\ExpressionLanguage
   *   The Expression Language.
   */
  public static function getExpressionLanguage(): ExpressionLanguage {
    if (!isset(static::$expressionLanguage)) {
      static::$expressionLanguage = new ExpressionLanguage();

      foreach (static::$expressionLanguageProviders as $provider_class) {
        static::$expressionLanguage->registerProvider(new $provider_class());
      }
    }

    return static::$expressionLanguage;
  }

  /**
   * Gets a validator object.
   *
   * @param string $validator_name
   *   The validator's name, as defined in static::$validators.
   *
   * @return \MutableTypedData\Validator\ValidatorInterface
   *   The validator instance.
   *
   * @throws \MutableTypedData\Exception\InvalidDefinitionException
   *   Throws an exception if the given string is not a defined validator name.
   */
  public static function getValidator(string $validator_name): ValidatorInterface {
    if (!isset(static::$validators[$validator_name])) {
      throw new InvalidDefinitionException(sprintf("Validator %s not found.",
        $validator_name
      ));
    }

    $class = static::$validators[$validator_name];
    return new $class;
  }

}
