<?php

namespace MutableTypedData\Test;

use MutableTypedData\Data\DataItem;
use MutableTypedData\Data\StringData;
use MutableTypedData\Data\ArrayData;
use MutableTypedData\Data\ComplexData;
use MutableTypedData\Data\MutableData;
use MutableTypedData\DataItemFactory;
use MutableTypedData\Definition\VariantDefinition;
use MutableTypedData\Definition\DataDefinition;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophet;

/**
 * Tests UI interaction.
 */
class DataInteractionTest extends TestCase {

  use VarDumperSetupTrait;

  protected Prophet $prophet;

  protected DataDefinition $definition;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setUpVarDumper();

    $this->prophet = new Prophet();

    $this->definition = DataDefinition::create('complex')
      ->setLabel('machine name')
      ->setRequired(TRUE)
      ->setProperties([
        'string_single' => DataDefinition::create('string')
          ->setLabel('machine name')
          ->setRequired(TRUE),
        'string_multiple' => DataDefinition::create('string')
          ->setLabel('machine name')
          ->setRequired(TRUE)
          ->setMultiple(TRUE),
        'internal' => DataDefinition::create('string')
          ->setInternal(TRUE),
        'complex_single' => DataDefinition::create('complex')
          ->setLabel('machine name')
          ->setRequired(TRUE)
          ->setProperties([
            'complex_single_alpha' => DataDefinition::create('string')
              ->setLabel('machine name')
              ->setRequired(TRUE),
            'complex_single_beta' => DataDefinition::create('string')
              ->setLabel('machine name')
              ->setRequired(TRUE),
            'internal' => DataDefinition::create('string')
              ->setInternal(TRUE),
          ]),
        'complex_multiple' => DataDefinition::create('complex')
          ->setLabel('machine name')
          ->setRequired(TRUE)
          ->setMultiple(TRUE)
          ->setProperties([
            'complex_multiple_alpha' => DataDefinition::create('string')
              ->setLabel('machine name')
              ->setRequired(TRUE),
            'complex_multiple_beta' => DataDefinition::create('string')
              ->setLabel('machine name')
              ->setRequired(TRUE),
            'internal' => DataDefinition::create('string')
              ->setInternal(TRUE),
          ]),
        'mutable_single' => DataDefinition::create('mutable')
          ->setLabel('machine name')
          ->setRequired(TRUE)
          ->setProperties([
            'type' => DataDefinition::create('string')
              ->setLabel('mutable type')
          ])
          ->setVariants([
            'alpha' => VariantDefinition::create()
              ->setLabel('Alpha')
              ->setProperties([
                'mutable_single_alpha_one' => DataDefinition::create('string')
                  ->setLabel('A1')
                  ->setRequired(TRUE),
                'mutable_single_alpha_two' => DataDefinition::create('string')
                  ->setLabel('A2')
                  ->setRequired(TRUE),
                'internal' => DataDefinition::create('string')
                  ->setInternal(TRUE),
              ]),
            'beta' => VariantDefinition::create()
              ->setLabel('Alpha')
              ->setProperties([
                'mutable_single_beta_one' => DataDefinition::create('string')
                  ->setLabel('B1')
                  ->setRequired(TRUE),
                'mutable_single_beta_two' => DataDefinition::create('string')
                  ->setLabel('B2')
                  ->setRequired(TRUE),
              ]),
          ]),
        'mutable_multiple' => DataDefinition::create('mutable')
          ->setLabel('machine name')
          ->setRequired(TRUE)
          ->setMultiple(TRUE)
          ->setProperties([
            'type' => DataDefinition::create('string')
              ->setLabel('mutable type')
          ])
          ->setVariants([
            'alpha' => VariantDefinition::create()
              ->setLabel('Alpha')
              ->setProperties([
                'mutable_multiple_alpha_one' => DataDefinition::create('string')
                  ->setLabel('A1')
                  ->setRequired(TRUE),
                'mutable_multiple_alpha_two' => DataDefinition::create('string')
                  ->setLabel('A2')
                  ->setRequired(TRUE),
              ]),
            'beta' => VariantDefinition::create()
              ->setLabel('Alpha')
              ->setProperties([
                'mutable_multiple_beta_one' => DataDefinition::create('string')
                  ->setLabel('B1')
                  ->setRequired(TRUE),
                'mutable_multiple_beta_two' => DataDefinition::create('string')
                  ->setLabel('B2')
                  ->setRequired(TRUE),
              ]),
          ]),
      ]);
  }

  /**
   * Tests a sequential UI.
   *
   * This is a UI that prompts for data one item at a time, such as a
   * command-line.
   */
  public function testSequentialUi() {
    $io_prophecy = $this->prophet->prophesize();
    $io_prophecy->willImplement(IoInterface::class);
    $io_prophecy
      ->prompt(Argument::type('string'))
      ->should(new \Prophecy\Prediction\CallbackPrediction(function($calls) {
        $property_names = [];
        foreach ($calls as $call) {
          $property_names[] = $call->getArguments()[0];
        }

        // Assert the order of UI prompts for data.
        Assert::assertEquals([
          'data:string_single',
          // Simple multiple item.
          'data:string_multiple:0',
          'data:string_multiple:1',
          // Complex single item.
          'data:complex_single:complex_single_alpha',
          'data:complex_single:complex_single_beta',
          // Complex multiple item.
          'data:complex_multiple:0:complex_multiple_alpha',
          'data:complex_multiple:0:complex_multiple_beta',
          'data:complex_multiple:1:complex_multiple_alpha',
          'data:complex_multiple:1:complex_multiple_beta',
          // Mutable single item.
          'data:mutable_single:type',
          'data:mutable_single:mutable_single_alpha_one',
          'data:mutable_single:mutable_single_alpha_two',
          // Mutable multiple item.
          'data:mutable_multiple:0:type',
          'data:mutable_multiple:0:mutable_multiple_alpha_one',
          'data:mutable_multiple:0:mutable_multiple_alpha_two',
          'data:mutable_multiple:1:type',
          'data:mutable_multiple:1:mutable_multiple_alpha_one',
          'data:mutable_multiple:1:mutable_multiple_alpha_two',
        ], $property_names);
      }));

    $ui = new SequentialUI($io_prophecy->reveal());

    // dump($this->definition);

    $data = DataItemFactory::createFromDefinition($this->definition);
    $ui->collectData($data);

    $this->prophet->checkPredictions();
  }

  /**
   * Tests an overview UI.
   *
   * This is a UI that shows all the data at once, such as an HTML form.
   */
  public function testOverviewUi() {
    $data = DataItemFactory::createFromDefinition($this->definition);

    $ui = new OverviewUI();
    $form = $ui->getForm($data);

    $expected = [
      'data:string_single',
      // Simple multiple item.
      'data:string_multiple:0',
      'data:string_multiple:1',
      // Complex single item.
      'data:complex_single:complex_single_alpha',
      'data:complex_single:complex_single_beta',
      // Complex multiple item.
      'data:complex_multiple:0:complex_multiple_alpha',
      'data:complex_multiple:0:complex_multiple_beta',
      'data:complex_multiple:1:complex_multiple_alpha',
      'data:complex_multiple:1:complex_multiple_beta',
      // Mutable single item.
      'data:mutable_single:type',
      // Mutable multiple item.
      'data:mutable_multiple:0:type',
      'data:mutable_multiple:1:type',
    ];
    $this->assertEquals(array_combine($expected, $expected), $form);

    // Pretend we did an ajax update and type is now set on all the mutable
    // data.
    $data->mutable_single->type->value = 'alpha';
    $data->mutable_multiple[0]->type->value = 'alpha';
    $data->mutable_multiple[1]->type->value = 'alpha';

    $form = $ui->getForm($data);

    // The mutable data item now has its properties.
    $expected = [
      'data:string_single',
      // Simple multiple item.
      'data:string_multiple:0',
      'data:string_multiple:1',
      // Complex single item.
      'data:complex_single:complex_single_alpha',
      'data:complex_single:complex_single_beta',
      // Complex multiple item.
      'data:complex_multiple:0:complex_multiple_alpha',
      'data:complex_multiple:0:complex_multiple_beta',
      'data:complex_multiple:1:complex_multiple_alpha',
      'data:complex_multiple:1:complex_multiple_beta',
      // Mutable single item.
      'data:mutable_single:type',
      'data:mutable_single:mutable_single_alpha_one',
      'data:mutable_single:mutable_single_alpha_two',
      // Mutable multiple item.
      'data:mutable_multiple:0:type',
      'data:mutable_multiple:0:mutable_multiple_alpha_one',
      'data:mutable_multiple:0:mutable_multiple_alpha_two',
      'data:mutable_multiple:1:type',
      'data:mutable_multiple:1:mutable_multiple_alpha_one',
      'data:mutable_multiple:1:mutable_multiple_alpha_two',
    ];
    $this->assertEquals(array_combine($expected, $expected), $form);
  }

}

/**
 * Basic sequential UI: mimics a command line UI.
 */
#[\AllowDynamicProperties]
class SequentialUI {

  public function __construct($io) {
    $this->io = $io;
  }

  public function collectData(DataItem $data) {
    if ($data->isMultiple()) {

      // FOR NOW, TODO TEMP! just add two items for ANY multiple property.
      // TODO: minimum cardinality and required!
      foreach ([0, 1] as $delta) {
        // Sequential UIs create an item when the user requests it.
        $delta_item = $data->createItem();

        $this->collectData($delta_item);
      }
    }
    elseif ($data->isComplex()) {
      foreach ($data as $data_item) {
        // dump($data_item);
        $this->collectData($data_item);
      }
    }
    else {
      $this->io->prompt($data->getAddress());

      // Simulate input setting the value for the 'type' property on mutable
      // data, so we get the next properties.
      if ($data->getName() == 'type') {
        $data->value = 'alpha';
      }
    }

    // dump($data);
  }

}

/**
 * Basic overview UI: mimics a form on a web page.
 */
class OverviewUI {

  public function getForm(DataItem $data) {
    $form = [];

    $this->getFormElement($form, $data);

    return $form;
  }

  protected function getFormElement(&$form, DataItem $data) {
    if ($data->isMultiple()) {
      // dump($data);
      // argh how do we handle this???
      // FOR NOW, TODO TEMP! just add two items for ANY multiple property.
      // TODO: minimum cardinality and required!
      foreach ([0, 1] as $delta) {
        if (!isset($data[$delta])) {
          // Sequential UIs create an item when the user requests it.
          $delta_item = $data->createItem();
        }
        else {
          $delta_item = $data[$delta];
        }

        $this->getFormElement($form, $delta_item);
      }
    }
    elseif ($data->isComplex()) {
      foreach ($data as $data_item) {
        $this->getFormElement($form, $data_item);
      }
    }
    else {
      $form[$data->getAddress()] = $data->getAddress();
    }
  }

}

/**
 * IO interface that we use to predict user interaction.
 */
interface IoInterface {

  public function prompt(string $prompt);

}
