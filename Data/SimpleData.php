<?php

namespace MutableTypedData\Data;

use MutableTypedData\Exception\InvalidAccessException;
use MutableTypedData\Exception\InvalidInputException;

/**
 * Represents data that has a single scalar value.
 *
 * To iterate over simple data, in order to treat both single and multiple
 * valued data the same, use items().
 *
 * The following code will act on the single value if $data is single-valued,
 * or on each delta if it's multi-valued:
 *
 * @code
 *   foreach ($data_might_be_single_or_multiple->items() as $item) {
 *     do_something($item->value);
 *   }
 * @endcode
 */
abstract class SimpleData extends DataItem implements \IteratorAggregate {

  public static function isSimple(): bool {
    return TRUE;
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

  /**
   * {@inheritdoc}
   */
  public function __set($name, $value) {
    if ($name != 'value') {
      throw new InvalidAccessException(sprintf("Only the 'value' property may be set on simple data at address %s.",
        $this->getAddress()
      ));
    }

    // Hand over to the set() method, since for simple data it does the same
    // thing.
    $this->set($value);
  }

  /**
   * {@inheritdoc}
   */
  public function set($value) {
    // dump("set $this->address with $value");
    // if ($this->address == 'module:readable_name') {
    //   dump(debug_backtrace());
    //   exit();
    // }
    // Check the type of the value.
    if (!is_scalar($value) && !is_null($value)) {
      throw new InvalidInputException(sprintf("Simple data may only have scalar or NULL values at address %s.",
       $this->getAddress()
      ));
    }

    $this->value = $value;
    $this->set = TRUE;

    // Bubble up.
    if ($this->parent) {
      $this->parent->onChange($this, $value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validate(/* ?bool $include_internal = FALSE */): array {
    $include_internal = func_get_args()[0] ?? FALSE;

    $violations = parent::validate($include_internal);

    if ($this->isEmpty() && $this->definition->isRequired()) {
      if ($this->definition->getDefault()) {
        $this->applyDefault();
      }
      else {
        $violations[$this->getAddress()][] = strtr('Value is required for @label.', [
          '@label' => $this->hasLabel()? $this->getLabel() : $this->getName(),
        ]);
      }
    }

    // Check a non-empty value satisfied the options.
    // Note that an empty string is considered non-empty, and so will likely
    // fail to satisfy the options. UIs should filter empty string responses.
    // TODO: document this more clearly and obviously.
    if (!$this->isEmpty() && $this->definition->hasOptions()) {
      $options = $this->definition->getOptions();

      if (!isset($options[$this->value])) {
        $violations[$this->getAddress()][] = strtr("Value '@value' is not one of the options for @label.", [
          '@value' => $this->value,
          '@label' => $this->getLabel(),
        ]);
      }
    }

    return $violations;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    // Values such as '0' and 0 are considered non-empty.
    // TODO: consider $set?
    return is_null($this->value);
  }

}

