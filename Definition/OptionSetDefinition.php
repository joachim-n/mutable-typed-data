<?php

namespace MutableTypedData\Definition;

use MutableTypedData\Data\DataItem;

/**
 * Defines a set of property options.
 *
 * These can be provided dynamically and can depend on the current data.
 */
class OptionSetDefinition {

  public function getOptions(DataItem $data): array {
    return [];
  }

}


// TODO! what happens if the dependent data changes!
// we need to track dependencies....
