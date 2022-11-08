<?php

namespace MutableTypedData\Data;

use MutableTypedData\Definition\DataDefinition;
use MutableTypedData\Exception\InvalidAccessException;
use MutableTypedData\Exception\InvalidDataAddressException;
use MutableTypedData\Exception\InvalidDefinitionException;
use MutableTypedData\Exception\InvalidInputException;

/**
 * Provides a base class for data items.
 */
abstract class DataItem {

  /**
   * The machine name of the data item.
   *
   * Root data which doesn't have a name set on the definition will get a name
   * of 'data' as a default.
   *
   * @var string
   */
  protected $name = NULL;

  /**
   * The value of the data item.
   *
   * @var mixed
   */
  protected $value;

  /**
   * Marks whether this data has had a value set or not.
   *
   * This applies to both externally-set values and default values that get
   * set by applyDefault().
   *
   * @var bool
   */
  protected $set;

  /**
   * The delta of this item if it's within multi-valued data.
   *
   * @var int
   */
  protected $delta;

  /**
   * The definition of the data item.
   *
   * @var \MutableTypedData\Definition\DataDefinition
   */
  protected $definition;

  /**
   * The callback that provides the definition for this data.
   *
   * This allows this data to be serialized, as the definition may contain
   * closures which cannot be serialized by PHP. This callback here, which is
   * set by the factory, allows the data to have its definition restored upon
   * unserialization.
   *
   * Accordingly, this callable property may not itself be a closure, and must
   * be either a function or a method callable.
   */
  protected $definitionSource;

  /**
   * The parent data item. NULL if this is the root.
   *
   * @var self
   */
  protected $parent;

  /**
   * Whether properties marked internal should be shown.
   *
   * @var bool
   */
  protected $revealInternal = FALSE;

  /**
   * The class name of the factory that created this data.
   *
   * Storing this here allows any customizations in a custom factory class to
   * persist when this data item creates new data items.
   *
   * @var \MutableTypedData\DataItemFactory
   */
  protected $factoryClass;

  /**
   * Tracks default dependencies during recursion to prevent circularity.
   *
   * @var array
   */
  protected static $seenDefaultDpendencies = [];

  /**
   * Holds the initial item address during default dependency recursion.
   *
   * @var string
   */
  protected static $startingDefaultAddress = '';

  /**
   * The data item's address in the structure. Lazily set by getAddress().
   *
   * @var string
   */
  protected $address = NULL;

  /**
   * The names of the properties on this that are included in serialization.
   */
  const SERIALIZABLE = [
    'value',
    'set',
    'name',
    'delta',
    'definitionSource',
    'factoryClass',
  ];

  /**
   * Constructor.
   *
   * @param \MutableTypedData\Definition\DataDefinition $definition
   *   The definition of this data.
   */
  public function __construct(DataDefinition $definition)  {
    $this->definition = $definition;

    // Set the root name to 'default' if the definition does not have a machine
    // name. (All non-root definitions will have a machine name.)
    $this->name = $definition->getName() ?? 'data';
  }

  /**
   * Sets the parent and delta of this data.
   *
   * This must be called immediately after construction for non-root items. It
   * is left separate from the constructor to allow manipulation of the data
   * structure.
   *
   * @internal
   *
   * @param self $parent
   *   The parent data item to set.
   * @param int $delta
   *   (optional) The delta this item has within the parent, if the parent is
   *   multiple-valued.
   */
  public function setParent(self $parent, int $delta = NULL) {
    $this->parent = $parent;

    if (is_int($delta)) {
      $this->delta = $delta;

      // A delta item gets the delta as the machine name.
      $this->name = $delta;
    }

    // Reset the address property.
    $this->getAddress();
  }

  /**
   * Sets the callback that obtains the definition for this data.
   *
   * @internal
   *
   * @param callable $callback
   *   The callback. This must be serializable: i.e. not an anonymous function.
   */
  public function setDefinitionSource(callable $callback) {
    if (isset($this->parent)) {
      throw new \LogicException("Can't set definition source on a non-root data item.");
    }

    $this->definitionSource = $callback;
  }

  /**
   * Gets whether this item is simple or not.
   *
   * @return bool
   *   TRUE if simple, FALSE if not.
   */
  abstract public static function isSimple(): bool;

  /**
   * Gets the items of this data as an array.
   *
   * This returns the data itself for single-valued simple data.
   *
   * Use this instead of iterating over the data object when you want to iterate
   * over the values of a data item without knowing whether that data is single
   * or multiple. However, this can NOT be used to recurse into child data,
   * since a data item that returns itself will cause an infinite loop.
   *
   * For example:
   * @code
   *   foreach ($data_might_be_single_or_multiple->items() as $item) {
   *     do_something($item->value);
   *   }
   * @endcode
   */
  abstract public function items(): array;

  /**
   * Sets this data to show its internal properties when iterated.
   *
   * @return static
   *   Returns itself for chaining.
   */
  public function showInternal(): self {
    $this->revealInternal = TRUE;

    $this->walk(function (DataItem $data) {
      $data->revealInternal = TRUE;
    });

    return $this;
  }

  /**
   * Sets the factory class this data was constructed with.
   *
   * @internal
   *
   * @param string $factory_class
   *   The name of the factory class.
   */
  public function setFactory(string $factory_class) {
    $this->factoryClass = $factory_class;
  }

  public function validate(): array {
    $violations = [];

    // Check all validators set on the data.
    if (!$this->isEmpty()) {
      foreach ($this->definition->getValidators() as $validator_name) {
        $validator = $this->factoryClass::getValidator($validator_name);

        if (!$validator->validate($this)) {
          $violations[$this->getAddress()][] = strtr($validator->message($this), [
            '@value' => $this->value,
            '@label' => $this->getLabel(),
          ]);

        }
      }
    }

    return $violations;
  }

  /**
   * Applies the default value to the data.
   *
   * @internal
   *
   * @return bool
   *   Whether the operation was successful:
   *    - TRUE if the default was applied successfully or a value was already
   *      present.
   *    - FALSE if there was a missing dependency.
   */
  public function applyDefault(bool $recursion = FALSE): bool {
    // Never overwrite a set value with a default.
    if ($this->set) {
      return TRUE;
    }

    // Keep track of adresses we've seen to prevent circular dependencies of
    // defaults.
    // If this is a fresh call, reset the addresses we've seen.
    if (!$recursion) {
      // Use class properties, as declaring function static variables means they
      // vary by class, and so different types of data don't share the data. The
      // class is hardcoded in case custom data types are defined that do not
      // inherit from DataItem.
      DataItem::$seenDefaultDpendencies = [];
      DataItem::$startingDefaultAddress = $this->getAddress();
    }

    if (isset(DataItem::$seenDefaultDpendencies[$this->getAddress()])) {
      throw new InvalidDefinitionException(sprintf("Circular default dependencies starting at %s, with dependency %s previously seen in dependencies %s.",
        DataItem::$startingDefaultAddress,
        $this->getAddress(),
        implode(', ', array_keys(DataItem::$seenDefaultDpendencies))
      ));
    }

    DataItem::$seenDefaultDpendencies[$this->getAddress()] = TRUE;

    if ($default = $this->definition->getDefault()) {
      // Report a failure if the dependencies are empty, allowing them to apply
      // defaults themselves.
      if ($dependencies = $default->getDependencies()) {
        foreach ($dependencies as $dependency_address) {
          try {
            // This instantiates the item, but does not apply its default.
            $dependent_item = $this->getItem($dependency_address);
          }
          catch (InvalidDataAddressException $e) {
            throw new InvalidDefinitionException(sprintf("Default for data at %s defines an invalid dependency '%s', data address exception message was: '%s'.",
              $this->getAddress(),
              $dependency_address,
              $e->getMessage()
            ));
          }

          // If the dependency has no value, try to apply its default.
          if (!$dependent_item->set) {
            $result = $dependent_item->applyDefault(TRUE);

            if (!$result) {
              // If applying the default to the dependency failed, then applying
              // the current default fails too.
              return FALSE;
            }

            // TODO: what's the point of this?????
            if (!$dependent_item->set) {
              return FALSE;
            }
          }
        }
      }

      switch ($default->getType()) {
        case 'literal':
          $literal = $default->getLiteral();
          $default_value = $literal;
          break;

        case 'expression':
          $expression = $default->getExpression();
          $expression_language = $this->factoryClass::getExpressionLanguage();
          try {
            $default_value = $expression_language->evaluate($expression, [
              'item' => $this,
              'parent' => $this->parent,
            ]);
          }
          catch (\Throwable $e) {
            throw new InvalidDefinitionException(sprintf("Error with default expression '%s' at address %s, error message was: %s, file %s, line %s.",
              $expression,
              $this->getAddress(),
              $e->getMessage(),
              $e->getFile(),
              $e->getLine()
            ));
          }

          break;

        case 'callable':
          $callable = $default->getCallable();
          try {
            $default_value = $callable($this);
          }
          catch (\Error $e) {
            throw new InvalidDefinitionException(sprintf("Error with default callable at address %s, error message was: %s, file %s, line %s.",
              $this->getAddress(),
              $e->getMessage(),
              $e->getFile(),
              $e->getLine()
            ));
          }

          break;
      }

      // If this data item is part of a multi-value set, then the default value
      // should be an array, and we take the value at the corresponding delta
      // offset.
      if ($this->isDelta()) {
        if (isset($default_value) && !is_array($default_value)) {
          throw new InvalidDefinitionException(sprintf(
            "Default value for multiple-valued data items must be an array at address %s.",
            $this->getAddress()
          ));
        }

        if (isset($default_value[$this->delta])) {
          $default_value = $default_value[$this->delta];
        }
      }

      if (isset($default_value)) {
        // If there's an input exception here, it's because the default value
        // is badly defined, so rethrow as a definition exception for clarity
        // and also to show the real source of the problem.
        try {
          $this->set($default_value);
        }
        catch (InvalidInputException $invalid_input_exception) {
          throw new InvalidDefinitionException(sprintf("Default value applied to data at %s caused an invalid input exception with message: %s",
            $this->getAddress(),
            $invalid_input_exception->getMessage()
          ));
        }
      }
    }

    return TRUE;
  }

  // argh name / machine name on definition! TODO!
  public function getName(): string {
    return $this->name;
  }

  /**
   * Returns whether this data item is part a multiple-valued set.
   *
   * @return bool
   *   TRUE if this data item is one of a multiple-valued set; FALSE if it is
   *   not.
   */
  public function isDelta(): bool {
    return (is_int($this->delta));
  }

  /**
   * Gets the address to this data item within the structure.
   *
   * A data item address is a string consisting of the names of all the
   * successive parents from the root and the name of the data item itself,
   * concatenated with a ':'. For example: 'root:grandparent:parent:the_item'.
   *
   * @return string
   *   The address.
   */
  public function getAddress(): string {
    if (!$this->address) {
      $parent = $this->getParent();

      if ($parent) {
        $parent_address = $this->getParent()->getAddress();

        if ($parent_address) {
          $address = $parent_address . ':';
        }
        else {
          $address = '';
        }

        $address .= $this->name;

        $this->address = $address;
      }
      else {
        $this->address = $this->name;
      }
    }

    return $this->address;
  }

  /**
   * Gets this data item's parent in the data structure.
   *
   * @return static|null
   *   The parent data item, or NULL if this data item is the root.
   */
  public function getParent(): ?self {
    return $this->parent;
  }

  /**
   * Gets the root data item of this item's data structure.
   *
   * @return static
   *   The root data item.
   */
  public function getRoot(): self {
    $ref = $this;

    while ($parent = $ref->getParent()) {
      $ref = $parent;
    }

    return $ref;
  }

  /**
   * Converts an address relative to the current data to an absolute address.
   *
   * @param string $relative_address
   *   A relative address. Note that this is not checked for validity: the
   *   GIGO principle applies!
   *
   * @return string
   *   The absolute address.
   */
  public function relativeAddressToAbsolute(string $relative_address): string {
    // If the address starts with the current item, the absolute address is just
    // a concatenation of this address + the relative address.
    if (substr($relative_address, 0, 2) === '.:') {
      $address_tail = substr($relative_address, 2);

      return $this->getAddress() . ':' . $address_tail;
    }

    // If the address starts with one or more parents, go up one for each parent
    // address piece.
    if (substr($relative_address, 0, 3) === '..:') {
      $relative_address_pieces = explode(':', $relative_address);

      $ref = $this;

      while ($relative_address_pieces && $relative_address_pieces[0] === '..') {
        $ref = $ref->getParent();
        $lop = array_shift($relative_address_pieces);
      }

      if ($relative_address_pieces) {
        return $ref->getAddress() . ':' . implode(':', $relative_address_pieces);
      }
      else {
        return $ref->getAddress();
      }
    }

    // The address was relative already.
    return $relative_address;
  }

  /**
   * Gets the child element at the specified address.
   *
   * @param string $address
   *   The address, either:
   *    - an absolute address, as returned by self::getAddress(). Any item in
   *      the structure can be called on with an absolute address.
   *    - a relative address in the format '.:CHILD:GRANDCHILD:ETC' to start
   *      from the current item.
   *    - a relative address in the format '..:SIBLING:ETC' to start
   *      from the current item's parent.
   *
   * @return static
   *   The data item.
   *
   * @throw \Exception
   *   Throws an exception if the address is invalid.
   */
  public function getItem(string $address): self {
    $ref = $this;

    $address_pieces = explode(':', $address);

    if ($address_pieces[0] == '.') {
      array_shift($address_pieces);
    }
    elseif ($address_pieces[0] !== '..') {
      $ref = $this->getRoot();

      // TODO: make ALL roots have a default machine name, then no condition
      // here.
      if ($ref->name) {
        array_shift($address_pieces);
      }
    }

    foreach ($address_pieces as $child_name) {
      if ($child_name == '..') {
        $ref = $ref->getParent();

        if (empty($ref)) {
          throw new InvalidDataAddressException(sprintf(
            "Unable to go higher than parent with address address '%s'",
            $address
          ));
        }
      }
      elseif (is_numeric($child_name)) {
        // Get a delta item from multiple-valued data.
        if (isset($ref[$child_name])) {
          $ref = $ref[$child_name];
        }
        else {
          throw new InvalidDataAddressException(sprintf("Unable to get child item '%s' from item with address '%s' in request for item '%s'.",
            $child_name,
            $ref->getAddress(),
            $address
          ));
        }
      }
      else {
        if ($ref->isSimple()) {
          throw new InvalidDataAddressException(sprintf(
            "Unable to get child item '%s' from simple item with address '%s'.",
            $address_component,
            $ref->getAddress()
          ));
        }

        // Get a property.
        if ($ref->hasProperty($child_name)) {
          $ref = $ref->get($child_name);
        }
        else {
          throw new InvalidDataAddressException(sprintf("Unable to get child item '%s' from item with address '%s' in request for item '%s'.",
            $child_name,
            $ref->getAddress(),
            $address
          ));
        }
      }
      // dump($ref);
    }

    return $ref;
  }

  public function getDefinition(): DataDefinition {
    return $this->definition;
  }

  public function getLabel() {
    $label = $this->definition->getLabel();

    if ($this->isDelta()) {
      // Use human-readable deltas; that is, starting at 1.
      $label .= ': ' . (string) ($this->delta + 1);
    }

    return $label;
  }

  /**
   * Magic setter.
   *
   * This can't get called on this class, since the class is abstract, but is
   * here for the benefit of child classes that only need the basic
   * functionality.
   */
  public function __set($name, $value) {
    // Complain, because ALL child classes should override this.
    throw new \Exception("Child classes of DataItem must override __set().");
  }

  /**
   * Sets the value of the data.
   *
   * For simple data, this is identical to setting with the 'value' property,
   * but for array or complex data, this allows the whole value to be set at
   * once.
   *
   * @param mixed $value
   *   The value to set.
   */
  public function set($value) {
    // Verify that child classes are overriding this.
    assert(in_array(debug_backtrace()[1]['function'], ['set', 'add', '__set']));

    // Child classes should have done the actual work of setting the value.
    // here we bubble up??
    if ($this->parent) {
      $this->parent->onChange($this, $value);
    }
  }

  public function add($value) {
    // For anything other than ArrayData, adding a value is the same as setting
    // it, as a new value can't be appended.
    $this->set($value);
  }

  /**
   * Imports data without throwing exceptions.
   *
   * This is for data that has been stored and which therefore may not match up
   * with the data definition which may have since changed in code.
   *
   * @param mixed $value
   *   The value.
   */
  public function import($value) {
    // For a simple value or array data, it's ok to call set().
    $this->set($value);
  }

  /**
   * Reacts to a child item's value being changed.
   *
   * @internal
   *
   * @param \MutableTypedData\Definition\DataDefinition $definition
   *   The definition of the changed data item.
   * @param string $name
   *   The property name on the item that was set.
   * @param mixed $value
   *   The value that was set.
   */
  public function onChange(DataItem $data_item, $value) {
    // Bubble up.
    if ($this->parent) {
      // TODO: should $name change as we bubble up? Or should we pass an
      // address?
      $this->parent->onChange($data_item, $value);
    }

  }

  public function __isset($name) {
    // Need to implement this so empty() works on $data_item->value.
    if ($name == 'value') {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  public function __get($name) {
    if ($name != 'value') {
      throw new InvalidAccessException("For a single-valued item, the only possible property is 'value', attempt to get '$name'.");
    }

    return $this->get();
  }

  /**
   * Gets the value.
   */
  public function get() {
    // TODO -- move this to simple data, no defalts on compound!
    if ($this->isEmpty() && $default = $this->definition->getDefault()) {
      // Apply the default if able.
      $this->applyDefault();
    }

    return $this->value;
  }

  /**
   * Gets the value as a plain array, without triggering any defaults.
   */
  protected function getRaw() {
    return $this->value;
  }

  /**
   * Accesses data.
   *
   * This causes defaults to be set and child data to be instantiated.
   */
  public function access(): void {
    // TODO: needs tests.
    $this->get();
  }

  /**
   * Removes this data item from the data.
   */
  public function unset(): void {
    if (!$this->parent) {
      throw new \Exception("unset() may not be called on the root data.");
    }

    $this->parent->removeItem($this->name);
  }

  /**
   * Determines whether the data is empty.
   *
   * Note that this:
   *  - does not cause defaults to be applied
   *  - considers only visible data.
   *
   * @return bool
   *   TRUE if emtpy, FALSE if not.
   *
   * TODO: needs tests.
   */
  abstract public function isEmpty(): bool;

  /**
   * Gets the value as a plain array.
   *
   * This will cause lazy defaults to be applied.
   */
  public function export() {
    return $this->get();
  }

  /**
   * Walks the whole data, applying a callback to each data item.
   *
   * @param callable $callback
   *   The callable to apply.
   */
  public function walk(callable $callback) {
    $callback($this);
  }

  /**
   * Allow calling through to the defintion.
   */
  public function __call(string $name, array $arguments) {
    // TODO: replace this, as it returns TRUE for protected methods!
    if (method_exists($this->definition, $name)) {
      return $this->definition->$name(...$arguments);
    }
    else {
      throw new \Exception('Call to undefined method ' . __CLASS__ . '::' . $name . '()');
      // TODO why doesn't this work?
      trigger_error('Call to undefined method ' . __CLASS__ . '::' . $name . '()', E_ERROR);
    }
  }

  /**
   * Magic method.
   *
   * Removes definitions when serializing, as they may contain callbacks.
   *
   * This relies on the definition itself being set with a callback rather than
   * an object.
   *
   * TODO: use Serializable interface instead!
   *
   * @return array
   */
  public function __sleep() {
    // If the root element has no definition source, then we can't serialize.
    if (!isset($this->parent) && !isset($this->definitionSource)) {
      throw new \Exception("Data that does not use a callback to provide its definition may not be serialized.");
    }

    // TODO: replace this with __serialize() when we depend on PHP 7.4, so
    // we can set 'value' to $this->export() and so serialize just the raw data
    // rather than all the child data items.
    return static::SERIALIZABLE;
  }

  /**
   * Magic method.
   *
   * Restore the definition removed by __sleep().
   */
  public function __wakeup() {
    // TODO: Replace this with __unserialize() when we depend on PHP 7.4, in
    // which we can just do import().
    // Only act on the parent, as that's the only one that has the definition
    // source.
    if (isset($this->parent)) {
      return;
    }

    // Only act if there is a definition callback set.
    if (!isset($this->definitionSource)) {
      return;
    }

    // Get the definition from the callback.
    $this->definition = ($this->definitionSource)();
    $this->properties = $this->definition->getProperties();

    $this->restoreOnWake();
  }

  /**
   * Helper for __wakeup().
   *
   * Recursively restores all properties of the data items whose values are
   * objects and were removed by __sleep().
   */
  protected function restoreOnWake() {
    // Nothing needed for most data types.
  }

}
