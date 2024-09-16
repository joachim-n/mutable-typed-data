<?php

namespace MutableTypedData\Definition;

/**
 * Defines the possible values for options sort order.
 *
 * Option weight always overrides this order. In other words, the order defined
 * with this value only applies to options of the same weight.
 */
enum OptionsSortOrder {

  /**
   * Options are sorted in the order in which they were added to the definition.
   */
  case Original;

  /**
   * Options are sorted by their label.
   */
  case Label;

}
