<?php

namespace MutableTypedData\Data;

use MutableTypedData\Exception\InvalidInputException;

class StringData extends SimpleData {

  /**
   * {@inheritdoc}
   */
  public function set($value) {
    // Check the type of the value.
    if (!is_scalar($value) && !is_null($value)) {
      $debug_value = is_a($value, DataItem::class) ? $value->export() : $value;

      throw new InvalidInputException(sprintf("String data may only have scalar or NULL values; got '%s' at address %s.",
        print_r($debug_value, TRUE),
        $this->getAddress()
      ));
    }

    // Cast a non-null scalar value to a string.
    if (!is_null($value)) {
      $value = (string) $value;
    }

    parent::set($value);
  }

}
