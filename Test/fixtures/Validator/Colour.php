<?php

namespace MutableTypedData\Fixtures\Validator;

use MutableTypedData\Data\DataItem;
use MutableTypedData\Validator\ValidatorInterface;

/**
 * Test validator.
 */
class Colour implements ValidatorInterface {

  /**
   * {@inheritdoc}
   */
  public function validate(DataItem $data): bool {
    return in_array($data->value, ['red', 'green', 'blue']);
  }

  /**
   * {@inheritdoc}
   */
  public function message(DataItem $data): string {
    return "The @label must be a colour, got '@value' instead.";
  }

}
