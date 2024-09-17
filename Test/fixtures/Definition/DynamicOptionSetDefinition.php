<?php

namespace MutableTypedData\Fixtures\Definition;

use MutableTypedData\Data\DataItem;
use MutableTypedData\Definition\DefaultDefinition;
use MutableTypedData\Definition\OptionDefinition;
use MutableTypedData\Definition\OptionSetDefininitionInterface;

class DynamicOptionSetDefinition implements OptionSetDefininitionInterface {

  /**
   * Array of definitions for getOptions() to return.
   *
   * Set this in the test.
   *
   * Keys are option values, values are option definition objects.
   *
   * @var \MutableTypedData\Definition\OptionDefinition[]
   */
  public static $definitions = [];

  public function getOptions(): array {
    return static::$definitions;
  }

}