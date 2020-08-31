<?php

namespace MutableTypedData\Fixtures\Factory;

use MutableTypedData\DataItemFactory;
use MutableTypedData\Data\StringData;
use MutableTypedData\Data\BooleanData;
use MutableTypedData\Data\ComplexData;
use MutableTypedData\Data\MutableData;

class FactoryWithListeningDataType extends DataItemFactory {

  /**
   * {@inheritdoc}
   */
  static protected $types = [
    'string' => StringData::class,
    'boolean' => BooleanData::class,
    'complex' => ComplexData::class,
    'mutable' => MutableData::class,
    'listening' => \MutableTypedData\Fixtures\Data\ListeningComplexData::class,
  ];

}
