<?php

namespace MutableTypedData\Definition;

/**
 * Defines a property option.
 */
class OptionDefinition {

  /**
   * The value for the option.
   *
   * @var mixed
   */
  protected $value;

  /**
   * The human-readable label for the option.
   *
   * @var string
   */
  protected $label;

  /**
   * An optional human-readable description for the option.
   *
   * @var string
   */
  protected $description = '';

  /**
   * An optional weight for sorting the option.
   *
   * @var int
   */
  protected $weight = 0;

  public function __construct($value, $label, $description = NULL, int $weight = 0) {
    $this->value = $value;
    $this->label = $label;
    if ($description) {
      $this->description = $description;
    }
    if ($weight) {
      $this->weight = $weight;
    }
  }

  /**
   * Factory method.
   *
   * @param mixed $value
   *   The data that is stored when this option is chosen.
   * @param string $label
   *   The label that is shown by UIs to the user.
   * @param string $description
   *   (optional) Additional text to show to the user in UIs.
   * @param int $weight
   *   (optional) The weight for sorting the option in the list. Larger numbers
   *   are heavier and sink to the bottom. Options with identical weights are
   *   shown in the sort order defined by the data definition.
   *
   * @return static
   */
  public static function create($value, string $label, string $description = NULL, int $weight = 0): self {
    return new static($value, $label, $description, $weight);
  }

  public function getValue() {
    return $this->value;
  }

  public function getLabel() {
    return $this->label;
  }

  public function getDescription() {
    return $this->description;
  }

  public function getWeight(): int {
    return $this->weight;
  }

}
