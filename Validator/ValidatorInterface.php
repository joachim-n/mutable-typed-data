<?php

namespace MutableTypedData\Validator;

use MutableTypedData\Data\DataItem;

/**
 * Interface for data validator.
 *
 * See MutableTypedData\Definition\DataDefinition::setValidators().
 */
interface ValidatorInterface {

  /**
   * Validates a data item.
   *
   * @param \MutableTypedData\Data\DataItem $data
   *   The data to validate.
   *
   * @return bool
   *   TRUE if the data is valid; FALSE is not.
   */
  public function validate(DataItem $data): bool;

  /**
   * Returns the message to show when validation fails.
   *
   * @param \MutableTypedData\Data\DataItem $data
   *   The data that was validated.
   *
   * @return string
   *   The message. The placeholders @label and @value may be used.
   */
  public function message(DataItem $data): string;

}
