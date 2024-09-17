<?php

namespace MutableTypedData\Definition;

use MutableTypedData\Data\DataItem;

/**
 * Interface for an option set definition.
 *
 * Option set definitions can provide options lazilly and dynamically.
 */
interface OptionSetDefininitionInterface {

  /**
   * Provides options for a property.
   *
   * @return \MutableTypedData\Definition\OptionDefinition[]
   *   An array of option definitions, keyed by the option ID.
   */
  public function getOptions(): array;

}
