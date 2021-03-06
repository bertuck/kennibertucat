<?php

/**
 * @file
 * Contains \Drupal\condition_test\Tests\OptionalContextConditionTest.
 */

namespace Drupal\condition_test\Tests;

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\node\Entity\Node;
use Drupal\simpletest\KernelTestBase;

/**
 * Tests a condition with optional context.
 *
 * @group condition_test
 */
class OptionalContextConditionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['system', 'user', 'condition_test', 'node'];

  /**
   * Tests with both contexts mapped to the same user.
   */
  protected function testContextMissing() {
    /** @var \Drupal\Core\Condition\ConditionPluginBase $condition */
    $condition = \Drupal::service('plugin.manager.condition')
      ->createInstance('condition_test_optional_context')
      ->setContextMapping([
        'node' => 'node',
      ]);
    \Drupal::service('context.handler')->applyContextMapping($condition, []);
    $this->assertTrue($condition->execute());
  }

  /**
   * Tests with both contexts mapped to the same user.
   */
  protected function testContextNoValue() {
    /** @var \Drupal\Core\Condition\ConditionPluginBase $condition */
    $condition = \Drupal::service('plugin.manager.condition')
      ->createInstance('condition_test_optional_context')
      ->setContextMapping([
        'node' => 'node',
      ]);
    $definition = new ContextDefinition('entity:node');
    $contexts['node'] = (new Context($definition));
    \Drupal::service('context.handler')->applyContextMapping($condition, $contexts);
    $this->assertTrue($condition->execute());
  }

  /**
   * Tests with both contexts mapped to the same user.
   */
  protected function testContextAvailable() {
    /** @var \Drupal\Core\Condition\ConditionPluginBase $condition */
    $condition = \Drupal::service('plugin.manager.condition')
      ->createInstance('condition_test_optional_context')
      ->setContextMapping([
        'node' => 'node',
      ]);
    $definition = new ContextDefinition('entity:node');
    $node = Node::create(['type' => 'example']);
    $contexts['node'] = (new Context($definition))->setContextValue($node);
    \Drupal::service('context.handler')->applyContextMapping($condition, $contexts);
    $this->assertFalse($condition->execute());
  }

}
