<?php

namespace MutableTypedData\Test;

use MutableTypedData\DataItemFactory;
use MutableTypedData\Definition\DataDefinition;
use MutableTypedData\Definition\OptionsSortOrder;
use PHPUnit\Framework\TestCase;

/**
 * Tests property definition behaviour.
 */
class DataDefinitionTest extends TestCase {

  use VarDumperSetupTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setUpVarDumper();
  }

  /**
   * Data provider.
   */
  public static function providerExceptions() {
    return [
      'set string properties' => [
        function() {
          DataDefinition::create('string')
            ->setProperties([]);
        }
      ],
      'set mutable multiple properties' => [
        function() {
          DataDefinition::create('string')
            ->setProperties([
              'type' => DataDefinition::create('string')
                ->setLabel('mutable type'),
              'bad' => DataDefinition::create('string')
                ->setLabel('mutable type')
            ]);
        }
      ],
      'set string variant' => [
        function() {
          DataDefinition::create('string')
            ->setVariants([]);
        }
      ],
      'set complex variant' => [
        function() {
          DataDefinition::create('complex')
            ->setVariants([]);
        }
      ],
      'set mutable variant without properties' => [
        function() {
          DataDefinition::create('mutable')
            ->setVariants([]);
        }
      ],
    ];
  }

  /**
   * Tests various scenarios with DataDefinition that throw an exception.
   *
   * @dataProvider providerExceptions
   *
   * @param callable $call
   *   The code to call that results in an exception.
   */
  public function testExceptions(callable $call) {
    $this->expectException(\Exception::class);

    $call();
  }

  /**
   * Tests adding child properties.
   */
  public function testDefineChildProperties() {
    $definition = DataDefinition::create('complex')
      ->setLabel('Label')
      ->setRequired(TRUE)
      ->setProperties([
        'alpha' => DataDefinition::create('string')
          ->setLabel('Label')
          ->setRequired(TRUE),
        'beta' => DataDefinition::create('string')
          ->setLabel('Label')
          ->setRequired(TRUE),
      ]);

    $this->assertEquals(['alpha', 'beta'], array_keys($definition->getProperties()));

    $definition->addProperty(DataDefinition::create('string')
      ->setName('gamma')
      ->setLabel('Label')
      ->setRequired(TRUE)
    );

    $this->assertEquals(['alpha', 'beta', 'gamma'], array_keys($definition->getProperties()));

    $this->expectException(\Exception::class);
    $definition->addProperty(DataDefinition::create('string')
      ->setLabel('Label')
      ->setRequired(TRUE)
    );
  }

  /**
   * Test sorting of options.
   */
  public function testOptionsOrder() {
    $definition = DataDefinition::create('string')
      ->setOptions(
        \MutableTypedData\Definition\OptionDefinition::create('dull', 'Dull', weight: 10),
        \MutableTypedData\Definition\OptionDefinition::create('normal_zulu', 'Normal Zulu'),
        \MutableTypedData\Definition\OptionDefinition::create('normal_alpha', 'Normal Alpha'),
        \MutableTypedData\Definition\OptionDefinition::create('important', 'Important', weight: -10),
      );

    $data = DataItemFactory::createFromDefinition($definition);
    $this->assertEquals([
      'important',
      'normal_zulu',
      'normal_alpha',
      'dull',
    ], array_keys($data->getOptions()));

    // Change the options to be sorted by label.
    $definition->setOptionsSorting(OptionsSortOrder::Label);
    $this->assertEquals([
      'important',
      'normal_alpha',
      'normal_zulu',
      'dull',
    ], array_keys($data->getOptions()));
  }

}
