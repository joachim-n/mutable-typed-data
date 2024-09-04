<?php

namespace MutableTypedData\Definition;

/**
 * Defines a property option.
 */
class OptionDefinition {

  protected $principal = FALSE;

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

  public function __construct($value, $label, $description = NULL) {
    $this->value = $value;
    $this->label = $label;
    if ($description) {
      $this->description = $description;
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
   *
   * @return static
   */
  public static function create($value, string $label, string $description = NULL): self {
    return new static($value, $label, $description);
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

}
