<?php

namespace MutableTypedData\Fixtures\Definition;

use MutableTypedData\Data\DataItem;
use MutableTypedData\Definition\DefaultDefinition;
use MutableTypedData\Definition\DefinitionProviderInterface;
use MutableTypedData\Definition\OptionDefinition;
use MutableTypedData\Definition\OptionsSortOrder;
use MutableTypedData\Definition\DataDefinition;
use MutableTypedData\Definition\VariantDefinition;

class SerializationTestDefinition implements DefinitionProviderInterface {

  public static function getDefinition(): DataDefinition {
    $definition = DataDefinition::create('complex')
      ->setProperties([
        'one' => DataDefinition::create('string'),
        'string_with_options' => DataDefinition::create('string')
          ->setOptionsArray([
            'option_one' => 'One',
            'option_two' => 'Two',
          ])
          ->setOptionsSorting(OptionsSortOrder::Label),
        'two' => DataDefinition::create('complex')
          ->setProperties([
            'alpha' => DataDefinition::create('string'),
            'beta' => DataDefinition::create('string'),
          ]),
        'default_from_closure' => DataDefinition::create('string')
          ->setDefault(DefaultDefinition::create()
            ->setCallable(
              function (DataItem $data) {
                return 'foo';
              }
            )),
        'default_from_expressiom' => DataDefinition::create('string')
          ->setDefault(DefaultDefinition::create()
            // This uses both the EL custom functions that are built into MTD
            // and the one added by the test.
            ->setExpression("uppercase(get('..:one')) ~ '-suffix'")
            ->setDependencies('..:one')),
        'empty_instantiated' => DataDefinition::create('string'),
        'empty_really' => DataDefinition::create('string'),
        'mutable' => DataDefinition::create('mutable')
          ->setProperties([
            'type' => DataDefinition::create('string')
          ])
          ->setVariants([
            'alpha' => VariantDefinition::create()
              ->setLabel('Alpha')
              ->setProperties([
                'alpha_one' => DataDefinition::create('string'),
                'alpha_two' => DataDefinition::create('string'),
              ]),
            'beta' => VariantDefinition::create()
              ->setLabel('Beta')
              ->setProperties([
                'beta_one' => DataDefinition::create('string'),
                'beta_two' => DataDefinition::create('string'),
              ]),
          ]),
        'mutable_empty_instantiated' => DataDefinition::create('mutable')
          ->setProperties([
            'type' => DataDefinition::create('string')
          ])
          ->setVariants([
            'empty_alpha' => VariantDefinition::create()
              ->setLabel('Alpha')
              ->setProperties([
                'empty_alpha_one' => DataDefinition::create('string'),
                'empty_alpha_two' => DataDefinition::create('string'),
              ]),
            'empty_beta' => VariantDefinition::create()
              ->setLabel('Beta')
              ->setProperties([
                'empty_beta_one' => DataDefinition::create('string'),
                'empty_beta_two' => DataDefinition::create('string'),
              ]),
          ]),
      ]);

    return $definition;
  }

}
