<?php

namespace MutableTypedData\Data;

use MutableTypedData\Exception\InvalidInputException;

/**
 * Represents boolean data.
 *
 * Data values are accessed with the 'value' property:
 *   $data->value = TRUE;
 *   $value = $data->value;
 *
 * As well as boolean TRUE or FALSE, this may be set with a 0 or 1 as either
 * integers or strings.
 */
class BooleanData extends SimpleData {

  /**
   * {@inheritdoc}
   */
  public function set($value) {
    // Allow the value to be set as 0 or 1 as a string or an integer.
    if ($value === 0 || $value === '0') {
      $value = FALSE;
    }
    if ($value === 1 || $value === '1') {
      $value = TRUE;
    }

    if (!is_bool($value) && !is_null($value)) {
      throw new InvalidInputException(sprintf("Boolean data may only have boolean or NULL values at address %s.",
       $this->getAddress()
      ));
    }

    parent::set($value);
  }

}
