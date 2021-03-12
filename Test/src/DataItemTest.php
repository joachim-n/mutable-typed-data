<?php

namespace MutableTypedData\Test;

use MutableTypedData\Data\DataItem;
use MutableTypedData\Data\StringData;
use MutableTypedData\Data\ArrayData;
use MutableTypedData\Data\ComplexData;
use MutableTypedData\Data\MutableData;
use MutableTypedData\DataItemFactory;
use MutableTypedData\Definition\DefaultDefinition;
use MutableTypedData\Definition\OptionDefinition;
use MutableTypedData\Definition\DataDefinition;
use MutableTypedData\Definition\VariantDefinition;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;

/**
 * Tests data item behaviour.
 */
class DataItemTest extends TestCase {

  use VarDumperSetupTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setUpVarDumper();
  }

  /**
   * Tests the data item factory returns the right classes.
   */
  public function testDataItemFactory() {
    $string_data = DataItemFactory::createFromDefinition(
      DataDefinition::create('string')
        ->setLabel('Label')
        ->setRequired(TRUE)
    );
    $this->assertEquals(StringData::class, get_class($string_data));

    $string_array_data = DataItemFactory::createFromDefinition(
      DataDefinition::create('string')
        ->setLabel('Label')
        ->setRequired(TRUE)
        ->setMultiple(TRUE)
    );
    $this->assertEquals(ArrayData::class, get_class($string_array_data));

    $complex_data = DataItemFactory::createFromDefinition(
      DataDefinition::create('complex')
        ->setLabel('Label')
        ->setRequired(TRUE)
    );
    $this->assertEquals(ComplexData::class, get_class($complex_data));

    $complex_array_data = DataItemFactory::createFromDefinition(
      DataDefinition::create('complex')
        ->setLabel('Label')
        ->setRequired(TRUE)
        ->setMultiple(TRUE)
    );
    $this->assertEquals(ArrayData::class, get_class($complex_array_data));

    $mutable_data = DataItemFactory::createFromDefinition(
      DataDefinition::create('mutable')
        ->setLabel('Label')
        ->setProperties([
          'type' => DataDefinition::create('string')
            ->setLabel('mutable type')
        ])
        ->setVariants([
          'alpha' => VariantDefinition::create()
            ->setLabel('Alpha')
            ->setProperties([
              'alpha_one' => DataDefinition::create('string')
                ->setLabel('A1')
                ->setRequired(TRUE),
            ]),
        ])
        ->setRequired(TRUE)
    );
    $this->assertEquals(MutableData::class, get_class($mutable_data));

    $mutable_array_data = DataItemFactory::createFromDefinition(
      DataDefinition::create('mutable')
        ->setLabel('Label')
        ->setRequired(TRUE)
        ->setMultiple(TRUE)
    );
    $this->assertEquals(ArrayData::class, get_class($mutable_array_data));
  }

  /**
   * Test getting the parent.
   */
  public function testGetParent() {
    $complex_data = DataItemFactory::createFromDefinition(
      DataDefinition::create('complex')
        ->setName('root')
        ->setProperties([
          'one' => DataDefinition::create('complex')
            ->setMultiple(TRUE)
            ->setProperties([
              'two' => DataDefinition::create('complex')
                ->setProperties([
                  'three' => DataDefinition::create('string')
                ])
            ]),
        ])
    );

    $complex_data->one[0]->two->three = 'foo';

    $this->assertEquals($complex_data->one[0]->two, $complex_data->one[0]->two->three->getParent());

    $this->assertEquals($complex_data->one[0], $complex_data->one[0]->two->three->getParent()->getParent());
    $this->assertEquals($complex_data->one[0], $complex_data->one[0]->two->getParent());

    $this->assertEquals($complex_data->one, $complex_data->one[0]->two->three->getParent()->getParent()->getParent());
    $this->assertEquals($complex_data->one, $complex_data->one[0]->getParent());

    $this->assertEquals($complex_data, $complex_data->one[0]->two->three->getParent()->getParent()->getParent()->getParent());
    $this->assertEquals($complex_data, $complex_data->one->getParent());
  }

  /**
   * Tests the addresses and labels of data items and using getItem().
   */
  public function testItemAddress() {
    // Data item without a root machine name.
    // The root name gets set to 'data' by default.
    $complex_data = DataItemFactory::createFromDefinition(
      DataDefinition::create('complex')
        ->setLabel('Root')
        ->setProperties([
          'one' => DataDefinition::create('complex')
            ->setLabel('One')
            ->setMultiple(TRUE)
            ->setProperties([
              'two' => DataDefinition::create('complex')
                ->setLabel('Two')
                ->setProperties([
                  'three' => DataDefinition::create('string')
                    ->setLabel('Three'),
                ])
            ]),
          'other' => DataDefinition::create('string'),
        ])
    );

    $complex_data->one[0]->two->three = 'foo';
    $complex_data->other = 'bar';

    $this->assertEquals('data:one:0:two:three', $complex_data->one[0]->two->three->getAddress());
    $this->assertEquals('data:one:0:two', $complex_data->one[0]->two->getAddress());
    $this->assertEquals('data:one:0', $complex_data->one[0]->getAddress());
    $this->assertEquals('data:one', $complex_data->one->getAddress());

    // Test relative item with the '.:' syntax.
    $this->assertEquals($complex_data->one[0]->two->three, $complex_data->getItem('.:one:0:two:three'));
    $this->assertEquals($complex_data->one[0]->two, $complex_data->getItem('.:one:0:two'));
    $this->assertEquals($complex_data->one[0], $complex_data->getItem('.:one:0'));
    $this->assertEquals($complex_data->one, $complex_data->getItem('.:one'));

    // Test getItem() with a relative address that goes to the parent.
    $this->assertEquals($complex_data->one[0]->two->three, $complex_data->one->getItem('..:one:0:two:three'));
    $this->assertEquals($complex_data, $complex_data->one->getItem('..'));
    $this->assertEquals($complex_data->one[0]->two, $complex_data->one->getItem('..:one:0:two'));
    $this->assertEquals($complex_data->other, $complex_data->one->getItem('..:other'));

    // Data item with a root machine name.
    $complex_data = DataItemFactory::createFromDefinition(
      DataDefinition::create('complex')
        ->setName('root')
        ->setLabel('Root')
        ->setRequired(TRUE)
        ->setProperties([
          'one' => DataDefinition::create('complex')
            ->setLabel('One')
            ->setRequired(TRUE)
            ->setMultiple(TRUE)
            ->setProperties([
              'two' => DataDefinition::create('complex')
                ->setLabel('Two')
                ->setRequired(TRUE)
                ->setProperties([
                  'three' => DataDefinition::create('string')
                    ->setLabel('Three')
                    ->setRequired(TRUE),
                ]),
              'two_a' => DataDefinition::create('string'),
            ])
        ])
    );

    $complex_data->one[0]->two->three = 'foo';
    $complex_data->one[1]->two->three = 'bar';

    $this->assertEquals('root:one:0:two:three', $complex_data->one[0]->two->three->getAddress());
    $this->assertEquals('root:one:0:two', $complex_data->one[0]->two->getAddress());
    $this->assertEquals('root:one:0', $complex_data->one[0]->getAddress());
    $this->assertEquals('root:one', $complex_data->one->getAddress());

    // Test getItem() with an absolute address.
    $this->assertEquals($complex_data->one[0]->two->three, $complex_data->getItem('root:one:0:two:three'));
    $this->assertEquals($complex_data->one[0]->two, $complex_data->getItem('root:one:0:two'));
    $this->assertEquals($complex_data->one[0], $complex_data->getItem('root:one:0'));
    $this->assertEquals($complex_data->one, $complex_data->getItem('root:one'));

    // Test getItem() with an absolute address on a child item.
    $this->assertEquals($complex_data->one[0]->two->three, $complex_data->one[0]->two->getItem('root:one:0:two:three'));
    $this->assertEquals($complex_data->one[0]->two, $complex_data->one[0]->two->getItem('root:one:0:two'));
    $this->assertEquals($complex_data->one[0], $complex_data->one[0]->two->getItem('root:one:0'));
    $this->assertEquals($complex_data->one, $complex_data->one[0]->two->getItem('root:one'));

    // Test relative addresses.
    $this->assertEquals($complex_data->one[0]->two->three, $complex_data->one[0]->two->getItem('.:three'));
    $this->assertEquals($complex_data->one[0]->two_a, $complex_data->one[0]->two->getItem('..:two_a'));
    $this->assertEquals($complex_data->one, $complex_data->one[0]->two->getItem('..:..'));
    $this->assertEquals($complex_data->one[1], $complex_data->one[0]->two->getItem('..:..:1'));

    // Test relativeAddressToAbsolute().
    $this->assertEquals('root:one:0:two:three', $complex_data->one[0]->two->relativeAddressToAbsolute('.:three'));
    $this->assertEquals('root:one:0:two_a', $complex_data->one[0]->two->relativeAddressToAbsolute('..:two_a'));
    $this->assertEquals('root:one:1', $complex_data->one[0]->two->relativeAddressToAbsolute('..:..:1'));
  }

  /**
   * Tests the labels of data items.
   */
  public function testItemLabels() {
    $complex_data = DataItemFactory::createFromDefinition(
      DataDefinition::create('complex')
        ->setLabel('Root')
        ->setRequired(TRUE)
        ->setProperties([
          'one' => DataDefinition::create('complex')
            ->setLabel('One')
            ->setRequired(TRUE)
            ->setMultiple(TRUE)
            ->setProperties([
              'two' => DataDefinition::create('complex')
                ->setLabel('Two')
                ->setRequired(TRUE)
                ->setProperties([
                  'three' => DataDefinition::create('string')
                    ->setLabel('Three')
                    ->setRequired(TRUE),
                ])
            ])
        ])
    );

    $complex_data->one[0]->two->three = 'foo';

    $this->assertEquals('Three', $complex_data->one[0]->two->three->getLabel());
    $this->assertEquals('Two', $complex_data->one[0]->two->getLabel());
    $this->assertEquals('One: 1', $complex_data->one[0]->getLabel());
    $this->assertEquals('One', $complex_data->one->getLabel());
  }

  /**
   * Tests that iterating over values works correctly.
   *
   * TODO: needs cleanup and testing items() too.
   */
  public function testIterators() {
    $definition = DataDefinition::create('string');
    $data = DataItemFactory::createFromDefinition($definition);
    foreach ($data as $item) {
      // Iterating over single simple data has no effect.
      $this->fail();
    }

    $definition = DataDefinition::create('complex')
      ->setLabel('Label')
      ->setRequired(TRUE)
      ->setProperties([
        'internal' => DataDefinition::create('string')
          ->setInternal(TRUE),
        'complex_multiple' => DataDefinition::create('mutable')
          ->setMultiple(TRUE)
          ->setProperties([
            'type' => DataDefinition::create('string')
              ->setLabel('mutable type')
          ])
          ->setVariantMapping([
            'a' => 'alpha',
            'b' => 'beta',
          ])
          ->setVariants([
            'alpha' => VariantDefinition::create()
              ->setLabel('Alpha')
              ->setProperties([
                'alpha_one' => DataDefinition::create('string')
                  ->setLabel('A1')
                  ->setRequired(TRUE),
                'internal' => DataDefinition::create('string')
                  ->setInternal(TRUE),
              ]),
            'beta' => VariantDefinition::create()
              ->setLabel('Beta')
              ->setProperties([
                'beta_one' => DataDefinition::create('string')
                  ->setLabel('B1')
                  ->setRequired(TRUE),
              ]),
          ]),
      ]);

    $data = DataItemFactory::createFromDefinition($definition);

    $array_data = [
      'complex_multiple' => [
        0 => [
          'type' => 'a',
          'alpha_one' => 'foo',
        ],
        1 => [
          'type' => 'b',
          'beta_one' => 'foo',
        ],
      ],
    ];

    // Set data so that values exist to start.
    $data->set($array_data);

    $this->assertEquals('a', $data->complex_multiple[0]->type->value);

    foreach ($data as $root_property_name => $data_item) {
      foreach ($data_item as $delta => $delta_item) {
        $this->assertEquals($array_data['complex_multiple'][$delta]['type'], $delta_item->type->value);

        foreach ($delta_item as $name => $mutable_data_item) {
          $this->assertEquals($array_data['complex_multiple'][$delta][$name], $mutable_data_item->value);
        }
      }
    }

    // Check iterating arbitrary single and multiple values.
    $definition = DataDefinition::create('complex')
      ->setProperties([
        'single' => DataDefinition::create('string'),
        'multiple' => DataDefinition::create('string')
          ->setMultiple(TRUE),
      ]);
    $data = DataItemFactory::createFromDefinition($definition);

    $data->single = 'foo';
    $data->multiple = ['one', 'two'];

    $seen = [];
    foreach ($data as $root_property_name => $data_item) {
      if ($data_item->isSimple()) {
        $seen[] = $data_item->value;
      }

      foreach ($data_item as $delta => $delta_item) {
        $seen[] = $delta_item->value;
      }
    }

    $this->assertEquals(['foo', 'one', 'two'], $seen);

    // TODO: test internal properties too.
    foreach ($data->showInternal() as $root_property_name => $data_item) {
      // dump($root_property_name);
    }
  }

  public function testBubbling() {
    $factory_class = \MutableTypedData\Fixtures\Factory\FactoryWithListeningDataType::class;

    $definition = DataDefinition::create('listening')
      ->setLabel('Label')
      ->setRequired(TRUE)
      ->setProperties([
        'single_string' => DataDefinition::create('string')
          ->setLabel('Label'),
        'single_complex' => DataDefinition::create('complex')
          ->setLabel('Label')
          ->setProperties([
            'single_complex_string' => DataDefinition::create('string')
              ->setLabel('Label'),
          ])
      ]);

    $complex_data = $factory_class::createFromDefinition($definition);

    // We need to set up a new listener each time we make a call, as Prophecy
    // doesn't easily allow checking for the order of calls.
    $listener = $this->prophesize();
    $listener->willExtend(DataItem::class);
    $listener->onChange(Argument::any(), 'value', 'one')->shouldBeCalledTimes(1);
    $complex_data->setListener($listener->reveal());

    $complex_data->single_string = 'one';

    $listener = $this->prophesize();
    $listener->willExtend(DataItem::class);
    $listener->onChange(Argument::any(), 'value', 'two')->shouldBeCalledTimes(1);
    $complex_data->setListener($listener->reveal());

    $complex_data->single_complex->single_complex_string = 'two';
  }

  /**
   * Test default values.
   *
   * @group defaults
   */
  public function testDefaults() {
    $definition = DataDefinition::create('complex')
      ->setName('complex_data')
      ->setProperties([
        'scalar_no_default' => DataDefinition::create('string'),
        'scalar_default' => DataDefinition::create('string')
          ->setDefault(DefaultDefinition::create()->setLiteral('foo')),
        'empty_string_default' => DataDefinition::create('string')
          ->setDefault(DefaultDefinition::create()->setLiteral('')),
        'multiple_scalar_default_no_default' => DataDefinition::create('string')
          ->setMultiple(TRUE),
        'multiple_scalar_default_one_value' => DataDefinition::create('string')
          ->setMultiple(TRUE)
          ->setDefault(DefaultDefinition::create()->setLiteral(['bar'])),
        'multiple_scalar_default_more_values' => DataDefinition::create('string')
          ->setMultiple(TRUE)
          ->setDefault(DefaultDefinition::create()->setLiteral(['bar', 'baz'])),
        'multiple_scalar_default_accessed_multiply' => DataDefinition::create('string')
          ->setMultiple(TRUE)
          ->setDefault(DefaultDefinition::create()->setLiteral(['bar', 'baz'])),
        'single_complex' => DataDefinition::create('complex')
          ->setProperties([
            'single_complex_string' => DataDefinition::create('string')
              ->setLabel('Label')
              ->setDefault(DefaultDefinition::create()
                // ->setExpression("data['complex_data']['scalar_default'] ~ '-suffix'")
                ->setExpression("get('complex_data:scalar_default') ~ '-suffix'")
                ->setDependencies('complex_data:scalar_default')
            ),
            'change_case' => DataDefinition::create('string')
              ->setDefault(DefaultDefinition::create()
                ->setExpression("uppercase(get('complex_data:scalar_default'))")
                ->setDependencies('complex_data:scalar_default')
            ),
            'relative_default' => DataDefinition::create('string')
              ->setDefault(DefaultDefinition::create()
                ->setExpression("get('..:change_case') ~ '-suffix'")
                ->setDependencies('complex_data:scalar_default')
              ),
            'lazy_default' => DataDefinition::create('string')
              ->setDefault(DefaultDefinition::create()
                ->setExpression("'evaluated'")
              ),
          ]),
          'multiple_complex_no_default' => DataDefinition::create('complex')
            ->setProperties([
              'alpha' => DataDefinition::create('string'),
              'beta' => DataDefinition::create('string'),
            ])
      ]);

    // DataItemFactory::setExpressionLanguageProvider(new ChangeCaseExpressionLanguageProvider());

    $complex_data = \MutableTypedData\Fixtures\Factory\FactoryWithChangeCaseExpressionLanguageFunctions::createFromDefinition($definition);

    $this->assertEmpty($complex_data->export(), "Default values are not initially set.");

    // TODO: check $complex_data->single_complex->single_complex_string; should not be set because dep not set.

    // Access properties to instantiate the data items and cause the default
    // value to be set.
    $complex_data->scalar_no_default;
    $this->assertNull($complex_data->scalar_no_default->get());

    $complex_data->scalar_default;
    $this->assertEquals('foo', $complex_data->scalar_default->value, "The literal default value was set.");

    $complex_data->empty_string_default;
    $this->assertSame('', $complex_data->empty_string_default->value, "The empty string default value was set.");

    // Set multi-valued defaults.
    $complex_data->multiple_scalar_default_no_default;
    // TODO: should get() work on ArrayData??
    $this->assertEquals([], $complex_data->multiple_scalar_default_no_default->get());

    $complex_data->multiple_scalar_default_one_value;
    $this->assertSame('bar', $complex_data->multiple_scalar_default_one_value[0]->value, "The multiple string default value was set.");

    $complex_data->multiple_scalar_default_more_values;
    $this->assertSame('bar', $complex_data->multiple_scalar_default_more_values[0]->value, "The multiple string default value was set.");
    $this->assertSame('baz', $complex_data->multiple_scalar_default_more_values[1]->value, "The multiple string default value was set.");

    $this->assertSame(['bar', 'baz'], $complex_data->multiple_scalar_default_accessed_multiply->values());

    // Set complex defaults.
    $complex_data->multiple_complex_no_default;
    $this->assertEquals([], $complex_data->multiple_complex_no_default->export());

    $complex_data->single_complex->single_complex_string;

    $this->assertEquals('foo-suffix', $complex_data->single_complex->single_complex_string->value, "The expression default value was set.");

    $complex_data->single_complex->change_case;

    $this->assertEquals('FOO', $complex_data->single_complex->change_case->value, "The expression default value was set.");

    $complex_data->single_complex->relative_default;

    $this->assertEquals('FOO-suffix', $complex_data->single_complex->relative_default->value, "The expression default value was set.");

    // Text lazy defaults.
    $complex_data->single_complex->lazy_default;
    $this->assertEquals('evaluated', $complex_data->single_complex->lazy_default->value, "The lazy default value was set.");

    // Test defaults don't get set after an empty value is set.
    // TODO: fill more of these in.
    $complex_data = \MutableTypedData\Fixtures\Factory\FactoryWithChangeCaseExpressionLanguageFunctions::createFromDefinition($definition);

    $complex_data->multiple_scalar_default_one_value = [];
    $this->assertSame([], $complex_data->multiple_scalar_default_one_value->items(), "The multiple string default value was set.");

    // Test defaults don't get set after an empty value is imported.
    // TODO: fill more of these in.
    $complex_data = \MutableTypedData\Fixtures\Factory\FactoryWithChangeCaseExpressionLanguageFunctions::createFromDefinition($definition);

    $complex_data->multiple_scalar_default_one_value->import([]);
    $this->assertSame([], $complex_data->multiple_scalar_default_one_value->items(), "The multiple string default value was set.");
  }

  /**
   * Test defaults with dependencies.
   *
   * @group defaults
   */
  public function testDefaultDependencies() {
    $definition = DataDefinition::create('complex')
      ->setProperties([
        'user_input' => DataDefinition::create('string'),
        // Depends directly on the user input property.
        'first_degree_dependent' => DataDefinition::create('string')
          ->setDefault(DefaultDefinition::create()
            // Use literal defaults for simplicity.
            ->setLiteral('foo')
            ->setDependencies('..:user_input')
          ),
        'second_degree_dependent_chain' => DataDefinition::create('string')
          ->setDefault(DefaultDefinition::create()
            ->setLiteral('foo')
            ->setDependencies('..:user_input')
        ),
        // Depends indirectly on the user input property.
        'second_degree_dependent' => DataDefinition::create('string')
        ->setDefault(DefaultDefinition::create()
          ->setLiteral('foo')
          ->setDependencies('..:second_degree_dependent_chain')
        ),
        // 'relative_dependent' =>
      ]);

    $complex_data = DataItemFactory::createFromDefinition($definition);

    // Defaults don't get applied when the dependent property is empty.
    $this->assertNull($complex_data->first_degree_dependent->value);
    $this->assertNull($complex_data->second_degree_dependent->value);

    $complex_data->user_input = 'user set value';

    $this->assertNotNull($complex_data->first_degree_dependent->value);
    $this->assertNotNull($complex_data->second_degree_dependent->value);
  }

  /**
   * Tests an exception is thrown for circular default dependencies.
   *
   * @group defaults
   */
  public function testCircularDefaultDependencies() {
    $definition = DataDefinition::create('complex')
      ->setProperties([
        'one' => DataDefinition::create('string')
          ->setDefault(DefaultDefinition::create()
            // Use literal defaults for simplicity.
            ->setLiteral('foo')
            ->setDependencies('..:two')
          ),
        // Use a different data type to test that the data item classes all
        // share the default dependencies tracking data.
        'two' => DataDefinition::create('boolean')
          ->setDefault(DefaultDefinition::create()
            ->setLiteral(TRUE)
            ->setDependencies('..:three')
          ),
        'two' => DataDefinition::create('string')
          ->setDefault(DefaultDefinition::create()
            ->setLiteral('foo')
            ->setDependencies('..:one')
          ),
      ]);

    $complex_data = DataItemFactory::createFromDefinition($definition);

    // It's ok to just instantiate the property.
    $complex_data->one;

    $this->expectException(\MutableTypedData\Exception\InvalidDefinitionException::class);
    $complex_data->one->value;

    // TODO: test different data types don't cause false positives.
  }

  /**
   * Tests setting values when there is a default.
   *
   * @group defaults
   */
  public function testOverridingDefaults() {
    $definition = DataDefinition::create('complex')
      ->setProperties([
        'multiple_scalar_default_more_values' => DataDefinition::create('string')
          ->setMultiple(TRUE)
          ->setDefault(DefaultDefinition::create()->setLiteral(['bar', 'baz'])),
      ]);

    $complex_data = DataItemFactory::createFromDefinition($definition);

    $complex_data->set([
      'multiple_scalar_default_more_values' => [],
    ]);

    $this->assertEquals([], $complex_data->multiple_scalar_default_more_values->get());
  }

  /**
   * Tests converting relative addresses to absolute in default expressions.
   */
  public function testDefaultExpressionAddressConversion() {
    $complex_data = DataItemFactory::createFromDefinition(
      DataDefinition::create('complex')
        ->setName('root')
        ->setProperties([
          'one' => DataDefinition::create('complex')
            ->setMultiple(TRUE)
            ->setProperties([
              'two' => DataDefinition::create('complex')
                ->setProperties([
                  'three' => DataDefinition::create('string')
                    ->setDefault(
                      DefaultDefinition::create()
                        ->setExpression("get('..:..:two_b') ~ 'cake' ~ get('..:..:..:1:two')")
                    ),
                ]),
              'two_b' => DataDefinition::create('string'),
            ]),
        ])
    );

    $default = $complex_data->one[0]->two->three->getDefault();

    $this->assertEquals("get('root:one:0:two_b') ~ 'cake' ~ get('root:one:1:two')", $default->getExpressionWithAbsoluteAddresses($complex_data->one[0]->two->three));
  }

  /**
   * Tests serializing data and restoring it.
   *
   * In particular, we need to check definitions that contain closures.
   */
  public function testSerialization() {
    // Use the factory that provides custom Expression Language functions, so
    // we test that those survive serialization.
    $complex_data = \MutableTypedData\Fixtures\Factory\FactoryWithChangeCaseExpressionLanguageFunctions::createFromProvider(\MutableTypedData\Fixtures\Definition\SerializationTestDefinition::class);
    $complex_data->one = 'value_one';
    $complex_data->two->alpha = 'value_A';
    $complex_data->two->beta = 'value_B';
    // Instantiate this (as a form submission might) but leave it empty.
    $complex_data->empty_instantiated;
    // TODO: array value!
    // Set mutable data to one variant, then change it. This tests that when
    // the data is unserialized, we don't have a problem with values being
    // applied for the wrong variant.
    $complex_data->mutable->type = 'alpha';
    $complex_data->mutable->alpha_one = 'foo';
    $complex_data->mutable->type = 'beta';
    $complex_data->mutable->beta_one = 'bar';

    // Instantiate mutable data that will remain empty. This simulates a UI
    // like a Drupal form displaying the data item, but the user not entering
    // anything.
    $complex_data->mutable_empty_instantiated->type;

    // Test that the data item object survives serialization.
    $pre_serialise_export = $complex_data->export();
    $serialised = serialize($complex_data);
    $complex_data = unserialize($serialised);

    // Check the export value is the same as before serialization
    $this->assertEquals($pre_serialise_export, $complex_data->export());

    // Check definitions were restored.
    $this->assertNotNull($complex_data->getDefinition());
    $this->assertEquals('one', $complex_data->one->getDefinition()->getName());
    $this->assertEquals('two', $complex_data->two->getDefinition()->getName());
    $this->assertEquals('alpha', $complex_data->two->alpha->getDefinition()->getName());
    $this->assertEquals('beta', $complex_data->two->beta->getDefinition()->getName());
    $this->assertEquals('empty_instantiated', $complex_data->empty_instantiated->getDefinition()->getName());

    // Instantiate to get defaults; check the default definitions survived the
    // serialization.
    $complex_data->default_from_closure;
    $complex_data->default_from_expressiom;

    $this->assertEquals('foo', $complex_data->default_from_closure->value);
    $this->assertEquals('VALUE_ONE-suffix', $complex_data->default_from_expressiom->value);
  }

  /**
   * Tests validation of required data.
   */
  public function testValidationRequired() {
    // Root single simple value.
    $string_data = DataItemFactory::createFromDefinition(
      DataDefinition::create('string')
        ->setLabel('Required data')
        ->setRequired(TRUE)
    );

    $violations = $string_data->validate();
    $this->assertCount(1, $violations, 'Required data fails validation if empty.');

    $string_data->set('one');

    $violations = $string_data->validate();
    $this->assertCount(0, $violations);

    // Root single complex value.
    $definition = DataDefinition::create('complex')
      ->setProperties([
        'req' => DataDefinition::create('string')
          ->setLabel('Is Required')
          ->setRequired(TRUE),
        'not_req' => DataDefinition::create('string'),
        'complex_not_req' => DataDefinition::create('complex')
          ->setProperties([
            'req' => DataDefinition::create('string')->setRequired(TRUE),
            'not_req' => DataDefinition::create('string'),
          ]),
      ]);

    $complex_data = DataItemFactory::createFromDefinition($definition);

    // Validation fails because the root complex data exists, and thus needs
    // the required property.
    $violations = $complex_data->validate();
    $this->assertCount(1, $violations);

    $complex_data->req = 'foo';

    $violations = $complex_data->validate();
    $this->assertCount(0, $violations);

    // Grandchild single simple value.
    $data = DataItemFactory::createFromDefinition(
      DataDefinition::create('complex')
        ->setProperties([
          'parent' => DataDefinition::create('complex')
            ->setProperties([
              'req' => DataDefinition::create('string')
                ->setLabel('Is Required')
                ->setRequired(TRUE),
            ]),
        ])
      );

    // The required data doesn't cause a validation error because its parent
    // doesn't exist.
    $violations = $data->validate();
    $this->assertCount(0, $violations);

    $data->parent->access();

    // Validation now fails because the parent value exists.
    $violations = $data->validate();
    $this->assertCount(1, $violations);

    $data->parent->req = 'foo';

    $violations = $data->validate();
    $this->assertCount(0, $violations);

    return;

    $string_data->set([]);

    $violations = $string_data->validate();
    $this->assertCount(1, $violations);


    dump($violations);
    // dump($complex_data->export());

    return;

    // Default gets set when validating.
    $string_data_with_default = DataItemFactory::createFromDefinition(
      DataDefinition::create('string')
        ->setLabel('Label')
        ->setRequired(TRUE)
        ->setDefault(DefaultDefinition::create()->setLiteral('foo'))
    );

    $violations = $string_data_with_default->validate();
    $this->assertCount(0, $violations, 'Required data passes validation if it has a default.');

    // Lazy default doesn't get set when validating.
    $definition = DataDefinition::create('string')
      ->setLabel('Label')
      ->setRequired(TRUE)
      ->setDefault(
        DefaultDefinition::create()
          ->setLiteral('foo')
        );

    $string_data_with_lazy_default = DataItemFactory::createFromDefinition($definition);
    $string_data_with_lazy_default->validate();
    $this->assertTrue($string_data_with_lazy_default->isEmpty(), 'Default is not set by validating.');
    $string_data_with_lazy_default->value;
    $this->assertEquals('foo', $string_data_with_lazy_default->value);

    // Required data fails validation.
    $definition = DataDefinition::create('string')
      ->setLabel('Label')
      ->setRequired(TRUE)
      ->setMultiple(TRUE);

    $string_array_data = DataItemFactory::createFromDefinition($definition);
    $violations = $string_array_data->validate();
    $this->assertCount(1, $violations, 'Required data fails validation if empty.');

    //
    $definition = DataDefinition::create('complex')
      ->setLabel('Label')
      ->setRequired(TRUE)
      ->setProperties([
        'req' => DataDefinition::create('string')
          ->setLabel('Is Required')
          ->setRequired(TRUE),
        'not_req' => DataDefinition::create('string')
          ->setLabel('Label'),
      ]);

    $complex_data = DataItemFactory::createFromDefinition($definition);
    $violations = $complex_data->validate();
    $this->assertCount(1, $violations, 'Required data fails validation if empty.');
    // The required value got created, but the not-required one did not.
    $this->assertEquals(['req' => NULL], $complex_data->export());
  }

  /**
   * Tests validation of options.
   */
  public function testValidationOptions() {
    $string_data = DataItemFactory::createFromDefinition(
      DataDefinition::create('string')
        ->setOptions(
          OptionDefinition::create('green', 'Emerald'),
          OptionDefinition::create('red', 'Magenta'),
          OptionDefinition::create('grey', 'Grey')
        )
        ->setLabel('Label')
        ->setRequired(TRUE)
    );
    $string_data->set('green');
    $this->assertEquals('green', $string_data->get());

    // Setting an invalid value fails validation.
    $string_data->set('cake');
    $this->assertEquals('cake', $string_data->get());

    $violations = $string_data->validate();
    $this->assertCount(1, $violations);
  }

  /**
   * Test use of validator classes.
   */
  public function testValidators() {
    $factory_class = \MutableTypedData\Fixtures\Factory\FactoryWithValidators::class;

    $definition = DataDefinition::create('string')
      ->setName('root')
      ->setLabel('Colour string')
      ->setValidators('colour');

    $string_data = $factory_class::createFromDefinition($definition);

    $violations = $string_data->validate();
    $this->assertCount(0, $violations);

    $string_data->value = 'cake';

    $violations = $string_data->validate();
    $this->assertCount(1, $violations);
    $this->assertArrayHasKey('root', $violations);
    $violation_messages = $violations['root'];
    $this->assertCount(1, $violation_messages);
    $violation_message = array_pop($violation_messages);
    $this->assertEquals("The Colour string must be a colour, got 'cake' instead.", $violation_message);

    $string_data->value = 'red';

    $violations = $string_data->validate();
    $this->assertCount(0, $violations);
  }

  // TODO: test isEmpty()!

  /**
   * Tests unsetting data.
   */
  public function testUnset() {
    // Complex data unset.
    $definition = DataDefinition::create('complex')
      ->setProperties([
        'unsetme' => DataDefinition::create('string')
      ]);

    $complex_data = DataItemFactory::createFromDefinition($definition);
    $this->assertEmpty($complex_data->export());

    $complex_data->unsetme = 'foo';
    $this->assertNotEmpty($complex_data->export());

    $complex_data->unsetme->unset();
    $this->assertEmpty($complex_data->export());

    // Array data unset.
    $definition = DataDefinition::create('string')
      ->setMultiple(TRUE);

    $array_data = DataItemFactory::createFromDefinition($definition);
    $this->assertEmpty($array_data->export());

    $array_data[] = 'foo';
    $this->assertNotEmpty($array_data->export());

    $array_data[0]->unset();
    $this->assertEmpty($array_data->export());

    // TODO: test throws exception on root.
  }

  /**
   * Data provider for testImportExportSingleItem().
   */
  public function providerImportExportSingleItem() {
    // List of the types to test: data definitions and the values that the test
    // should set on them.
    $definitions = [
      'string_multiple_empty' => [
        'definition' => DataDefinition::create('string')
          ->setName('property')
          ->setMultiple(TRUE),
        'value_initial_export' => [],
        'value_after_import' => [],
      ],
      'string_multiple_empty_with_default' => [
        'definition' => DataDefinition::create('string')
          ->setName('property')
          ->setMultiple(TRUE)
          ->setLiteralDefault(['a', 'b']),
        // This is only used when this definition is at the root.
        'value_initial_export' => ['a', 'b'],
        'value_after_import' => [],
      ],
    ];

    // The data definitions are fitted into various host definitions as well as
    // solo, so we can for example test a multi-valued empty string at root
    // level, within a complex item, and within a complex multiple item.
    $hosts = [
      // Complex single.
      'in_complex_single' => [
        // The type definition gets set as a property on this definition.
        'definition' => DataDefinition::create('complex'),
        'value_initial_export' => [],
        'value_after_import_template' => [
          // This value gets replaced with the type's 'value_after_import'.
          'property' => NULL,
        ],
      ],
      'in_complex_multiple' => [
        'definition' => DataDefinition::create('complex')
          ->setMultiple(TRUE),
        'value_initial_export' => [],
        'value_after_import_template' => [
          0 => [
            'property' => NULL,
          ],
        ],
      ],
    ];

    $data = [];
    foreach ($definitions as $definition_key => $definition) {
      $data[$definition_key . '_at_root'] = [
        'definition' => $definition['definition'],
        'value_initial_export' => $definition['value_initial_export'],
        'value_after_import' => $definition['value_after_import'],
      ];

      foreach ($hosts as $host_key => $host) {
        // Insert the data item's expected export value into the host's
        // template.
        $new_value = $host['value_after_import_template'];
        array_walk_recursive($new_value, function(&$value, $key, $replacements) {
          if (isset($replacements[$key])) {
            $value = $replacements[$key];
          }
        }, ['property' => $definition['value_after_import']]);

        $data[$definition_key . '_' . $host_key] = [
          'definition' => $host['definition']->addProperty($definition['definition']),
          'value_initial_export' => $host['value_initial_export'],
          'value_after_import' => $new_value,
        ];
      }
    }

    return $data;
  }

  /**
   * Tests import and export of data as arrays.
   *
   * @dataProvider providerImportExportSingleItem
   *
   * @param \MutableTypedData\Definition\DataDefinition $definition
   *   The data definition.
   * @param mixed $value_initial_export
   *   The exported export value of the data without setting any value on it.
   * @param mixed $value_after_import
   *   The value to set on the data, which should then match the export.
   */
  public function testImportExportSingleItem(DataDefinition $definition, $value_initial_export, $value_after_import) {
    // dump($definition);
    $data = DataItemFactory::createFromDefinition($definition);

    $export = $data->export();
    $this->assertEquals($value_initial_export, $export);

    $data->set($value_after_import);

    $export = $data->export();
    $this->assertEquals($value_after_import, $export);
  }

  /**
   * Tests import and export of data as arrays.
   *
   * TODO: fold this into testImportExportSingleItem() as single items, which
   * are easier to debug.
   */
  public function testImportExport() {
    $definition = DataDefinition::create('complex')
      ->setName('root')
      ->setLabel('Label')
      ->setRequired(TRUE)
      ->setProperties([
        'one' => DataDefinition::create('complex')
          ->setLabel('Label')
          ->setRequired(TRUE)
          ->setMultiple(TRUE)
          ->setProperties([
            'one_a' => DataDefinition::create('complex')
              ->setLabel('Label')
              ->setRequired(TRUE)
              ->setProperties([
                'three' => DataDefinition::create('string')
                  ->setLabel('Label')
                  ->setRequired(TRUE),
              ])
          ]),
        'two' => DataDefinition::create('mutable')
          ->setLabel('my label')
          ->setMultiple(TRUE)
          ->setProperties([
            'type' => DataDefinition::create('string')
              ->setLabel('mutable type')
          ])
          ->setVariantMapping([
            'a' => 'alpha',
            'b' => 'beta',
          ])
          ->setVariants([
            'alpha' => VariantDefinition::create()
              ->setLabel('Alpha')
              ->setProperties([
                'alpha_one' => DataDefinition::create('string')
                  ->setLabel('A1')
                  ->setRequired(TRUE),
                'alpha_two' => DataDefinition::create('string')
                  ->setLabel('A2')
                  ->setRequired(TRUE),
              ]),
            'beta' => VariantDefinition::create()
              ->setLabel('Beta')
              ->setProperties([
                'beta_one' => DataDefinition::create('string')
                  ->setLabel('B1')
                  ->setRequired(TRUE),
                'beta_two' => DataDefinition::create('string')
                  ->setLabel('B2')
                  ->setRequired(TRUE),
              ]),
          ]),
      ]);

    $complex_data = DataItemFactory::createFromDefinition($definition);

    $array_data = [
      'one' => [
        0 => [
          'one_a' => [
            'three' => 'foo',
          ],
        ],
        1 => [
          'one_a' => [
            'three' => '76',
          ],
        ],
      ],
      'two' => [
        0 => [
          'type' => 'a',
          'alpha_one' => 'foo',
        ],
        1 => [
          'type' => 'b',
          'beta_one' => 'foo',
        ],
      ],
    ];

    $complex_data->set($array_data);

    $export = $complex_data->export();

    $this->assertEquals($array_data, $export);
  }

  /**
   * Tests import when the definition has been updated.
   *
   * This is for things that store data as an exported array and then want to
   * load the data back into a data item after the underlying code definition
   * has been changed by a version update.
   */
  public function testImportWithDefinitionUpdate() {
    $old_definition = DataDefinition::create('complex')
      ->setLabel('Label')
      ->setRequired(TRUE)
      ->setProperties([
        'stays' =>  DataDefinition::create('string')
          ->setLabel('Label'),
        'old' => DataDefinition::create('complex')
          ->setLabel('Label')
          ->setRequired(TRUE)
          ->setMultiple(TRUE)
          ->setProperties([
            'old_two' => DataDefinition::create('string')
              ->setLabel('Label')
              ->setRequired(TRUE)
          ]),
        'child_changes' => DataDefinition::create('complex')
          ->setMultiple(TRUE)
          ->setProperties([
            'old' => DataDefinition::create('string')
          ]),
      ]);

    $new_definition = DataDefinition::create('complex')
      ->setLabel('Label')
      ->setRequired(TRUE)
      ->setProperties([
        'stays' =>  DataDefinition::create('string')
          ->setLabel('Label'),
        'new' => DataDefinition::create('complex')
          ->setLabel('Label')
          ->setRequired(TRUE)
          ->setMultiple(TRUE)
          ->setProperties([
            'new_two' => DataDefinition::create('string')
              ->setLabel('Label')
              ->setRequired(TRUE)
          ]),
        'child_changes' => DataDefinition::create('complex')
          ->setMultiple(TRUE)
          ->setProperties([
            'new' => DataDefinition::create('string')
          ]),

      ]);

    $complex_data = DataItemFactory::createFromDefinition($old_definition);
    $complex_data->set([
      'old' => [
        0 => [
          'old_two' => 'foo',
        ],
      ],
      'stays' => 'bar',
      'child_changes' => [
        0 => [
          'old' => 'biz',
        ]
      ],
    ]);

    $export = $complex_data->export();

    $complex_data = DataItemFactory::createFromDefinition($new_definition);

    $complex_data->import($export);

    $re_export = $complex_data->export();

    $this->assertArrayNotHasKey('old', $re_export);
  }

  /**
   * Tests walking data.
   */
  public function testWalk() {
    $definition = DataDefinition::create('complex')
      ->setName('root')
      ->setLabel('Label')
      ->setRequired(TRUE)
      ->setProperties([
        'one_simple' => DataDefinition::create('string'),
        'two_multiple' => DataDefinition::create('string')
          ->setMultiple(TRUE),
        'three_complex' => DataDefinition::create('complex')
          ->setMultiple(TRUE)
          ->setProperties([
            'walked_one' => DataDefinition::create('string'),
            'empty_not_walked' => DataDefinition::create('string'),
            'walked_two' => DataDefinition::create('string'),
          ]),
        'two' => DataDefinition::create('string'),
      ]);

    $data = DataItemFactory::createFromDefinition($definition);

    $data->one_simple->value = 'foo';
    $data->two_multiple[] = 'foo';
    $data->two_multiple[] = 'foo';
    $data->three_complex[0]->walked_one->value = 'foo';
    $data->three_complex[0]->walked_two->value = 'foo';
    $data->three_complex[1]->walked_one->value = 'foo';
    $data->three_complex[1]->walked_two->value = 'foo';
    $data->two->value = 'foo';

    $callback_seen_addresses = [];

    $callback = function(DataItem $data) use (&$callback_seen_addresses) {
      $callback_seen_addresses[] = $data->getAddress();
    };

    $data->walk($callback);

    $expected_addresses = [
      'root',
      'root:one_simple',
      'root:two_multiple',
      'root:two_multiple:0',
      'root:two_multiple:1',
      'root:three_complex',
      'root:three_complex:0',
      'root:three_complex:0:walked_one',
      'root:three_complex:0:walked_two',
      'root:three_complex:1',
      'root:three_complex:1:walked_one',
      'root:three_complex:1:walked_two',
      'root:two',
    ];

    $this->assertEquals($expected_addresses, $callback_seen_addresses);

    $data->walk([$this, 'walkCallback']);
  }

  /**
   * Method callback for testWalk().
   */
  public function walkCallback(DataItem $data) {
    // TODO: maintain DRY by allowing extra parameters to walk()? But faffy to
    // do that just for the benefit of tests!
    static $expected_addresses = [
      'root',
      'root:one_simple',
      'root:two_multiple',
      'root:two_multiple:0',
      'root:two_multiple:1',
      'root:three_complex',
      'root:three_complex:0',
      'root:three_complex:0:walked_one',
      'root:three_complex:0:walked_two',
      'root:three_complex:1',
      'root:three_complex:1:walked_one',
      'root:three_complex:1:walked_two',
      'root:two',
    ];

    $address = array_shift($expected_addresses);

    $this->assertEquals($address, $data->getAddress());
  }

  /**
   * Tests single string data items.
   */
  public function testSingleStringData() {
    $definition = DataDefinition::create('string')
      ->setLabel('Label')
      ->setRequired(TRUE);

    $string_data = DataItemFactory::createFromDefinition($definition);

    $this->assertNull($string_data->value);
    $this->assertNull($string_data->get());

    $string_data->set('one');
    $this->assertEquals('one', $string_data->get());

    // Setting again replaces the prior value.
    $string_data->set('two');
    $this->assertEquals('two', $string_data->get());
    $this->assertEquals('two', $string_data->value);

    // Using the value property.
    $string_data->value = 'three';
    $this->assertEquals('three', $string_data->get());
    $this->assertEquals('three', $string_data->value);

    // Non-string scalars are cast.
    $string_data->value = 47;
    $this->assertSame('47', $string_data->value);
    $this->assertFalse($string_data->isEmpty());

    $string_data->value = TRUE;
    $this->assertSame('1', $string_data->value);
    $this->assertFalse($string_data->isEmpty());

    // Setting empty and non-empty value.
    $string_data->value = NULL;
    $this->assertSame(NULL, $string_data->value);
    $this->assertTrue($string_data->isEmpty());
    $this->assertTrue(empty($string_data->value));

    $string_data->value = '';
    $this->assertSame('', $string_data->value);
    $this->assertFalse($string_data->isEmpty());
    $this->assertTrue(empty($string_data->value));

    $string_data->value = '0';
    $this->assertSame('0', $string_data->value);
    $this->assertFalse($string_data->isEmpty());
    $this->assertTrue(empty($string_data->value));

    $string_data->value = 0;
    $this->assertSame('0', $string_data->value);
    $this->assertFalse($string_data->isEmpty());
    $this->assertTrue(empty($string_data->value));
  }

  public function providerSingleStringDataExceptions() {
    return [
      'not_value' => [
        'not_value',
        'foo',
        \MutableTypedData\Exception\InvalidAccessException::class,
      ],
      'array' => [
        'value',
        [],
      ],
      'object' => [
        'value',
        new \StdClass(),
      ],
    ];
  }

  /**
   * Tests exceptions.
   *
   * @dataProvider providerSingleStringDataExceptions
   */
  public function testSingleStringDataExceptions($property, $value, $exception_class = \MutableTypedData\Exception\InvalidInputException::class) {
    $string_data = DataItemFactory::createFromDefinition(
      DataDefinition::create('string')
        ->setLabel('label')
    );

    $this->expectException($exception_class);
    $string_data->{$property} = $value;
  }

  /**
   * Tests multi-valued string data items.
   */
  public function testMultipleStringData() {
    // now string data that's multivalued....
    $definition = DataDefinition::create('string')
      ->setLabel('Label')
      ->setRequired(TRUE)
      ->setMultiple(TRUE)
      ->setMaxCardinality(4);

    $string_array_data = DataItemFactory::createFromDefinition($definition);

    $this->assertTrue($string_array_data->mayAddItem());

    // Use the empty index operator to create an array item.
    $string_array_data[] = 'one';
    $this->assertEquals('one', $string_array_data[0]->value);
    $this->assertEquals(['one'], $string_array_data->values());
    $this->assertTrue($string_array_data->mayAddItem());

    // Set the next delta.
    $string_array_data[1] = 'one';
    $this->assertEquals('one', $string_array_data[0]->value);
    $this->assertEquals('one', $string_array_data[1]->value);
    $this->assertEquals(['one', 'one'], $string_array_data->values());
    $this->assertTrue($string_array_data->mayAddItem());

    // Set an existing delta.
    $string_array_data[0] = 'zero';
    $this->assertEquals('zero', $string_array_data[0]->value);
    $this->assertEquals('one', $string_array_data[1]->value);
    $this->assertEquals(['zero', 'one'], $string_array_data->values());

    // Setting a scalar will auto-create an ArrayData as the value here and then
    // multiple sets will add items.
    // TODO: remove this behaviour -- not useful????
    $string_array_data->set('two');
    $this->assertEquals('zero', $string_array_data[0]->value);
    $this->assertEquals('one', $string_array_data[1]->value);
    $this->assertEquals('two', $string_array_data[2]->value);
    $this->assertTrue($string_array_data->mayAddItem());
    $this->assertEquals(['zero', 'one', 'two'], $string_array_data->values());

    // Use createItem() to add a new item.
    $string_data = $string_array_data->createItem();
    $string_data->set('three');
    $this->assertFalse($string_array_data->mayAddItem());

    $this->assertEquals('zero', $string_array_data[0]->value);
    $this->assertEquals('one', $string_array_data[1]->value);
    $this->assertEquals('two', $string_array_data[2]->value);
    $this->assertEquals('three', $string_array_data[3]->value);
    $this->assertEquals(['zero', 'one', 'two', 'three'], $string_array_data->values());

    // Use add() to add items.
    $string_array_data->set(['one', 'two']);
    $string_array_data->add('three');
    $this->assertEquals(['one', 'two', 'three'], $string_array_data->values());

    $string_array_data->set(['one', 'two']);
    $string_array_data->add(['three', 'four']);
    $this->assertEquals(['one', 'two', 'three', 'four'], $string_array_data->values());

    // Use set() to set completely new data.
    $string_array_data->set(['zero', 'one', 'two', 'three']);
    $this->assertEquals(['zero', 'one', 'two', 'three'], $string_array_data->values());

    // Test removal of items: middle, start, end.
    unset($string_array_data[2]);
    $this->assertEquals('zero', $string_array_data[0]->value);
    $this->assertEquals('one', $string_array_data[1]->value);
    // Deltas reindex.
    $this->assertEquals('three', $string_array_data[2]->value);
    $this->assertEquals(['zero', 'one', 'three'], $string_array_data->values());

    unset($string_array_data[0]);
    $this->assertEquals('one', $string_array_data[0]->value);
    $this->assertEquals('three', $string_array_data[1]->value);
    $this->assertEquals(['one', 'three'], $string_array_data->values());

    unset($string_array_data[1]);
    $this->assertEquals('one', $string_array_data[0]->value);
    $this->assertEquals(['one'], $string_array_data->values());

    // Create from an array.
    $string_array_data = DataItemFactory::createFromDefinition($definition);
    $string_array_data->set([
      0 => 'zero',
      1 => 'one',
    ]);
    $this->assertEquals('zero', $string_array_data[0]->value);
    $this->assertEquals('one', $string_array_data[1]->value);
    $this->assertEquals(['zero', 'one'], $string_array_data->values());

    // Check corner case of setting empty array.
    $string_array_data = DataItemFactory::createFromDefinition($definition);
    $string_array_data->set([]);
    $this->assertEmpty($string_array_data->export());
    $this->assertEquals([], $string_array_data->values());

    // Check inserting new item.
    $string_array_data = DataItemFactory::createFromDefinition($definition);
    $string_array_data->set([
      0 => 'zero',
      1 => 'one',
      2 => 'two',
    ]);

    $new_item = $string_array_data->insertBefore(2);
    $new_item->value = 'extra';

    foreach ([0, 1, 2, 3] as $delta) {
      $this->assertEquals($delta, $string_array_data[$delta]->getName());
      $this->assertEquals(sprintf("Label: %s", $delta + 1), $string_array_data[$delta]->getLabel());
    }

    $this->assertEquals('zero', $string_array_data[0]->value);
    $this->assertEquals('one', $string_array_data[1]->value);
    $this->assertEquals('extra', $string_array_data[2]->value);
    $this->assertEquals('two', $string_array_data[3]->value);
    $this->assertEquals(['zero', 'one', 'extra', 'two'], $string_array_data->values());
  }

  /**
   * Data provider for testMultipleStringDataExceptions().
   */
  public function providerMultipleStringDataExceptions() {
    return [
      'object_access' => [
        function($string_array_data) {
          $foo = $string_array_data->bad;
        }
      ],
      'object_set' => [
        function($string_array_data) {
          $string_array_data->bar = 'foo';
        }
      ],
      'non_int_access' => [
        function($string_array_data) {
          $foo = $string_array_data['bad'];
        }
      ],
      'non_int_set' => [
        function($string_array_data) {
          $string_array_data['bad'] = 'foo';
        }
      ],
      'skip_delta' => [
        function($string_array_data) {
          $string_array_data[1] = 'foo';
        }
      ],
      'access_bad_delta' => [
        function($string_array_data) {
          $string_array_data[0] = 'foo';
          $foo = $string_array_data[2];
        }
      ],
      'set_nonsequential_keys' => [
        function($string_array_data) {
          $string_array_data->set([1 => 'foo']);
        }
      ],
      'over_cardinality_append' => [
        function($string_array_data) {
          $string_array_data[] = 'one';
          $string_array_data[] = 'two';
          $string_array_data[] = 'three';
          $string_array_data[] = 'four';
          $string_array_data[] = 'OVER';
        }
      ],
      'over_cardinality_set' => [
        function($string_array_data) {
          $string_array_data->set(['one', 'two', 'three', 'four', 'OVER']);
        }
      ],
      'over_cardinality_add_one' => [
        function($string_array_data) {
          $string_array_data->set(['one', 'two', 'three', 'four']);
          $string_array_data->add('OVER');
        }
      ],
      'over_cardinality_add_multiple' => [
        function($string_array_data) {
          $string_array_data->set(['one', 'two', 'three']);
          $string_array_data->add(['four', 'OVER']);
        }
      ],
      'exception_from_child_data' => [
        function($string_array_data) {
          $string_array_data[0] = [];
        }
      ],
    ];
  }

  /**
   * Tests exceptions on multiple string data.
   *
   * This covers general cases for array data.
   *
   * @dataProvider providerMultipleStringDataExceptions
   */
  public function testMultipleStringDataExceptions(callable $call) {
    $definition = DataDefinition::create('string')
      ->setMultiple(TRUE)
      ->setMaxCardinality(4);

    $string_array_data = DataItemFactory::createFromDefinition($definition);

    // TODO: specialise exception.
    $this->expectException(\Exception::class);
    $call($string_array_data);
  }

  /**
   * Tests boolean data.
   */
  public function testSingleBooleanData() {
    $boolean_data = DataItemFactory::createFromDefinition(
      DataDefinition::create('boolean')
        ->setLabel('label')
    );

    $boolean_data->value = TRUE;
    $this->assertEquals(TRUE, $boolean_data->value);

    $boolean_data->value = 1;
    $this->assertEquals(TRUE, $boolean_data->value);

    $boolean_data->value = FALSE;
    $this->assertEquals(FALSE, $boolean_data->value);


    $this->expectException(\Exception::class);
    $boolean_data->value = 'string';
  }

  /**
   * Tests single complex data item.
   */
  public function testSingleComplexData() {
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

    $complex_data = DataItemFactory::createFromDefinition($definition);

    $complex_data->alpha = 'foo';
    $this->assertEquals('foo', $complex_data->alpha->value);

    $complex_data->beta = 'bar';
    $this->assertEquals('bar', $complex_data->beta->value);

    $complex_data = DataItemFactory::createFromDefinition($definition);
    $complex_data->set([
      'alpha' => 'a',
      'beta' => 'b',
    ]);
    $this->assertEquals('a', $complex_data->alpha->value);
    $this->assertEquals('b', $complex_data->beta->value);

    // Test empty() and isEmpty().
    $complex_data = DataItemFactory::createFromDefinition($definition);
    $this->assertTrue($complex_data->isEmpty());

    $this->assertTrue($complex_data->alpha->isEmpty());
    $this->assertTrue(empty($complex_data->alpha->value));

    $complex_data->alpha = '';
    $this->assertFalse($complex_data->alpha->isEmpty());
    $this->assertTrue(empty($complex_data->alpha->value));


    $complex_data->alpha = '0';
    $this->assertFalse($complex_data->alpha->isEmpty());
    $this->assertTrue(empty($complex_data->alpha->value));

    $complex_data->alpha = 'foo';
    $this->assertFalse($complex_data->alpha->isEmpty());
    $this->assertFalse(empty($complex_data->alpha->value));
  }

  /**
   * Data provider for testSingleComplexDataExceptions().
   */
  public function providerSingleComplexDataExceptions() {
    return [
      'set_single_bad_property' => [
        function($complex_data) {
          $complex_data->bad = 'foo';
        }
      ],
      'set_contains_bad_property' => [
        function($complex_data) {
          $complex_data->set(['bad' => 'foo']);
        }
      ],
      'set_scalar' => [
        function($complex_data) {
          $complex_data->set('foo');
        }
      ],
      'exception_from_child_data' => [
        function($complex_data) {
          $complex_data->alpha = [];
        }
      ],
    ];
  }

  /**
   * Tests exceptions on complex data.
   *
   * @dataProvider providerSingleComplexDataExceptions
   */
  public function testSingleComplexDataExceptions(callable $call) {
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
    $complex_data = DataItemFactory::createFromDefinition($definition);

    $this->expectException(\Exception::class);
    $call($complex_data);
  }

  /**
   * Tests multiple complex data item.
   */
  public function testMultipleComplexData() {
    $definition = DataDefinition::create('complex')
      ->setLabel('Label')
      ->setRequired(TRUE)
      ->setMultiple(TRUE)
      ->setProperties([
        'alpha' => DataDefinition::create('string')
          ->setLabel('Label')
          ->setRequired(TRUE),
        'beta' => DataDefinition::create('string')
          ->setLabel('Label')
          ->setRequired(TRUE),
      ]);

    $complex_array_data = DataItemFactory::createFromDefinition($definition);

    // Use the empty index operator to create an array item.
    $complex_array_data[] = [
      'alpha' => '0a',
    ];
    $this->assertEquals('0a', $complex_array_data[0]->alpha->value);

    // Set new values with the array append notation.
    $complex_array_data[]->alpha = '1a';
    $this->assertEquals('1a', $complex_array_data[1]->alpha->value);

    // Set the next delta.
    $complex_array_data[2] = [
      'alpha' => '2a',
    ];
    $this->assertEquals('0a', $complex_array_data[0]->alpha->value);
    $this->assertEquals('1a', $complex_array_data[1]->alpha->value);
    $this->assertEquals('2a', $complex_array_data[2]->alpha->value);

    // Set the next delta with chained syntax.
    $complex_array_data[3]->alpha = '3a';

    // Set an existing delta.
    $complex_array_data[0]->alpha = '00a';
    $this->assertEquals('00a', $complex_array_data[0]->alpha->value);
    $this->assertEquals('1a', $complex_array_data[1]->alpha->value);

    // Use createItem() to add a new item.
    $complex_data = $complex_array_data->createItem();
    $complex_data->alpha = '4a';

    $this->assertEquals('00a', $complex_array_data[0]->alpha->value);
    $this->assertEquals('1a', $complex_array_data[1]->alpha->value);
    $this->assertEquals('2a', $complex_array_data[2]->alpha->value);

    // Test setting deep values with an array.
    $complex_array_data = DataItemFactory::createFromDefinition($definition);

    $complex_array_data->set([
      0 => [
        'alpha' => '0a',
      ],
      1 => [
        'beta' => '1b',
      ],
      2 => [
        'alpha' => '2a',
        'beta' => '2b',
      ],
    ]);

    $this->assertEquals('0a', $complex_array_data[0]->alpha->value);
    $this->assertEquals('1b', $complex_array_data[1]->beta->value);
    $this->assertEquals('2a', $complex_array_data[2]->alpha->value);
    $this->assertEquals('2b', $complex_array_data[2]->beta->value);
  }

  /**
   * Tests single mutable data item.
   */
  public function testSingleMutableData() {
    $definition = DataDefinition::create('mutable')
      ->setLabel('my label')
      // TODO: consider a dedicated method for this setTypeProperty()?
      ->setProperties([
        'type' => DataDefinition::create('string')
          ->setLabel('mutable type')
      ])
      ->setVariants([
        'alpha' => VariantDefinition::create()
          ->setLabel('Alpha')
          ->setProperties([
            'alpha_one' => DataDefinition::create('string')
              ->setLabel('A1')
              ->setRequired(TRUE),
            'alpha_two' => DataDefinition::create('string')
              ->setLabel('A2')
              ->setRequired(TRUE),
          ]),
        'beta' => VariantDefinition::create()
          ->setLabel('Beta')
          ->setProperties([
            'beta_one' => DataDefinition::create('string')
              ->setLabel('B1')
              ->setRequired(TRUE),
            'beta_two' => DataDefinition::create('string')
              ->setLabel('B2')
              ->setRequired(TRUE),
          ]),
      ]);

    $mutable_data = DataItemFactory::createFromDefinition($definition);

    $this->assertTrue($mutable_data->type->hasOptions());

    $mutable_data->type = 'alpha';
    $mutable_data->alpha_one = 'foo';
    $mutable_data->alpha_two = 'bar';

    $this->assertEquals('alpha', $mutable_data->type->value);
    $this->assertEquals('foo', $mutable_data->alpha_one->value);
    $this->assertEquals('bar', $mutable_data->alpha_two->value);

    // Change the variant.
    $mutable_data->type = 'beta';
    $mutable_data->beta_one = 'foo';
    $mutable_data->beta_two = 'bar';

    $this->assertEquals('beta', $mutable_data->type->value);
    $this->assertEquals('foo', $mutable_data->beta_one->value);
    $this->assertEquals('bar', $mutable_data->beta_two->value);

    $mutable_data = DataItemFactory::createFromDefinition($definition);

    $mutable_data->type = 'beta';
    $mutable_data->beta_one = 'foo';
    $mutable_data->beta_two = 'bar';

    $this->assertEquals('beta', $mutable_data->type->value);
    $this->assertEquals('foo', $mutable_data->beta_one->value);
    $this->assertEquals('bar', $mutable_data->beta_two->value);

    // Also test setting the variant property with set() rather than magic
    // setters.
    $mutable_data = DataItemFactory::createFromDefinition($definition);

    $mutable_data->type->set('alpha');
    $mutable_data->alpha_one = 'foo';
    $mutable_data->alpha_two = 'bar';

    $this->assertEquals('alpha', $mutable_data->type->value);
    $this->assertEquals('foo', $mutable_data->alpha_one->value);
    $this->assertEquals('bar', $mutable_data->alpha_two->value);

    // TODO: expand this into a data provider for all the possible crash
    // scenarios!
    $mutable_data = DataItemFactory::createFromDefinition($definition);
    $this->expectException(\Exception::class);
    $mutable_data->alpha_one = 'foo';
  }

  /**
   * Data provider for testSingleComplexDataExceptions().
   */
  public function providerSingleMutableDataExceptions() {
    return [
      'set_property_before_type' => [
        function($mutable_data) {
          $mutable_data->alpha_one = 'foo';
        }
      ],
      // All the same exceptions as complex data.
      'set_single_bad_property' => [
        function($mutable_data) {
          $mutable_data->type = 'alpha';
          $mutable_data->bad = 'foo';
        }
      ],
      'set_contains_bad_property' => [
        function($mutable_data) {
          $mutable_data->type = 'alpha';
          $mutable_data->set(['bad' => 'foo']);
        }
      ],
      'set_scalar' => [
        function($mutable_data) {
          $mutable_data->type = 'alpha';
          $mutable_data->set('foo');
        }
      ],
      'exception_from_child_data' => [
        function($mutable_data) {
          $mutable_data->type = 'alpha';
          $mutable_data->alpha = [];
        }
      ],
    ];
  }

  /**
   * Tests exceptions on complex data.
   *
   * @dataProvider providerSingleComplexDataExceptions
   */
  public function testSingleMutableDataExceptions(callable $call) {
    $definition = DataDefinition::create('mutable')
      ->setLabel('my label')
      ->setProperties([
        'type' => DataDefinition::create('string')
          ->setLabel('mutable type')
      ])
      ->setVariants([
        'alpha' => VariantDefinition::create()
          ->setLabel('Alpha')
          ->setProperties([
            'alpha_one' => DataDefinition::create('string')
              ->setLabel('A1')
              ->setRequired(TRUE),
            'alpha_two' => DataDefinition::create('string')
              ->setLabel('A2')
              ->setRequired(TRUE),
          ]),
        'beta' => VariantDefinition::create()
          ->setLabel('Beta')
          ->setProperties([
            'beta_one' => DataDefinition::create('string')
              ->setLabel('B1')
              ->setRequired(TRUE),
            'beta_two' => DataDefinition::create('string')
              ->setLabel('B2')
              ->setRequired(TRUE),
          ]),
      ]);

    $mutable_data = DataItemFactory::createFromDefinition($definition);

    $this->expectException(\Exception::class);
    $call($mutable_data);
  }

  /**
   * Tests single mutable data item with derived type.
   */
  public function testMutableDataDerivedType() {
    $definition = DataDefinition::create('mutable')
      ->setLabel('my label')
      ->setProperties([
        'type' => DataDefinition::create('string')
          ->setLabel('mutable type')
          ->setOptionsArray([
            // Silly example where the variant property options don't match
            // up to the variant names.
            'a' => 'Alpha',
            'aleph' => 'Aleph',
            'alpha' => 'Alpha',
            'b' => 'Beta',
          ])
        ])
      ->setVariantMapping([
        'a' => 'alpha',
        'aleph' => 'alpha',
        'alpha' => 'alpha',
        'b' => 'beta',
      ])
      ->setVariants([
        'alpha' => VariantDefinition::create()
          ->setLabel('Alpha')
          ->setProperties([
            'alpha_one' => DataDefinition::create('string')
              ->setLabel('A1')
              ->setRequired(TRUE),
            'alpha_two' => DataDefinition::create('string')
              ->setLabel('A2')
              ->setRequired(TRUE),
          ]),
        'beta' => VariantDefinition::create()
          ->setLabel('Beta')
          ->setProperties([
            'beta_one' => DataDefinition::create('string')
              ->setLabel('B1')
              ->setRequired(TRUE),
            'beta_two' => DataDefinition::create('string')
              ->setLabel('B2')
              ->setRequired(TRUE),
          ]),
      ]);

    $mutable_data = DataItemFactory::createFromDefinition($definition);

    $this->assertTrue($mutable_data->type->hasOptions());

    $mutable_data->type = 'aleph';
    $mutable_data->alpha_one = 'foo';
    $mutable_data->alpha_two = 'bar';

    $this->assertEquals('aleph', $mutable_data->type->value);
    $this->assertEquals('foo', $mutable_data->alpha_one->value);
    $this->assertEquals('bar', $mutable_data->alpha_two->value);
  }

  /**
   * Tests multiple mutable data item.
   */
  public function testMultipleMutableData() {
    $definition = DataDefinition::create('mutable')
      ->setLabel('my label')
      ->setProperties([
        'type' => DataDefinition::create('string')
          ->setLabel('mutable type')
      ])
      ->setMultiple(TRUE)
      ->setVariants([
        'alpha' => VariantDefinition::create()
          ->setLabel('Alpha')
          ->setProperties([
            'alpha_one' => DataDefinition::create('string')
              ->setLabel('A1')
              ->setRequired(TRUE),
            'alpha_two' => DataDefinition::create('string')
              ->setLabel('A2')
              ->setRequired(TRUE),
          ]),
        'beta' => VariantDefinition::create()
          ->setLabel('Alpha')
          ->setProperties([
            'beta_one' => DataDefinition::create('string')
              ->setLabel('B1')
              ->setRequired(TRUE),
            'beta_two' => DataDefinition::create('string')
              ->setLabel('B2')
              ->setRequired(TRUE),
          ])
      ]);

    $mutable_array_data = DataItemFactory::createFromDefinition($definition);

    $mutable_data = $mutable_array_data->createItem();
    $mutable_data->type = 'alpha';
    $mutable_data->alpha_one = 'foo';
    $mutable_data->alpha_two = 'bar';

    // TODO: allow this:
    /*
    $mutable_data[0]->type = 'alpha';
    $mutable_data[0]->alpha_one = 'foo';
    $mutable_data[0]->alpha_two = 'bar';
    */

    $mutable_data = $mutable_array_data->createItem();
    $mutable_data->type = 'beta';
    $mutable_data->beta_one = 'foo';
    $mutable_data->beta_two = 'bar';

    $this->assertEquals('alpha', $mutable_array_data[0]->type->value);
    $this->assertEquals('beta', $mutable_array_data[1]->type->value);
  }

}
