<?php

/**
 * @file
 * Contains \Drupal\editor\Tests\EditorManagerTest.
 */

namespace Drupal\editor\Tests;

use Drupal\simpletest\KernelTestBase;
use Drupal\editor\Plugin\EditorManager;

/**
 * Tests detection of text editors and correct generation of attachments.
 *
 * @group editor
 */
class EditorManagerTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('system', 'user', 'filter', 'editor');

  /**
   * The manager for text editor plugins.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $editorManager;

  protected function setUp() {
    parent::setUp();

    // Install the Filter module.
    $this->installSchema('system', 'url_alias');

    // Add text formats.
    $filtered_html_format = entity_create('filter_format', array(
      'format' => 'filtered_html',
      'name' => 'Filtered HTML',
      'weight' => 0,
      'filters' => array(),
    ));
    $filtered_html_format->save();
    $full_html_format = entity_create('filter_format', array(
      'format' => 'full_html',
      'name' => 'Full HTML',
      'weight' => 1,
      'filters' => array(),
    ));
    $full_html_format->save();
  }

  /**
   * Tests the configurable text editor manager.
   */
  public function testManager() {
    $this->editorManager = $this->container->get('plugin.manager.editor');

    // Case 1: no text editor available:
    // - listOptions() should return an empty list of options
    // - getAttachments() should return an empty #attachments array (and not
    //   a JS settings structure that is empty)
    $this->assertIdentical(array(), $this->editorManager->listOptions(), 'When no text editor is enabled, the manager works correctly.');
    $this->assertIdentical(array(), $this->editorManager->getAttachments(array()), 'No attachments when no text editor is enabled and retrieving attachments for zero text formats.');
    $this->assertIdentical(array(), $this->editorManager->getAttachments(array('filtered_html', 'full_html')), 'No attachments when no text editor is enabled and retrieving attachments for multiple text formats.');

    // Enable the Text Editor Test module, which has the Unicorn Editor and
    // clear the editor manager's cache so it is picked up.
    $this->enableModules(array('editor_test'));
    $this->editorManager = $this->container->get('plugin.manager.editor');
    $this->editorManager->clearCachedDefinitions();

    // Case 2: a text editor available.
    $this->assertIdentical('Unicorn Editor', (string) $this->editorManager->listOptions()['unicorn'], 'When some text editor is enabled, the manager works correctly.');

    // Case 3: a text editor available & associated (but associated only with
    // the 'Full HTML' text format).
    $unicorn_plugin = $this->editorManager->createInstance('unicorn');
    $editor = entity_create('editor', array(
      'format' => 'full_html',
      'editor' => 'unicorn',
    ));
    $editor->save();
    $this->assertIdentical(array(), $this->editorManager->getAttachments(array()), 'No attachments when one text editor is enabled and retrieving attachments for zero text formats.');
    $expected = array(
      'library' => array(
        0 => 'editor_test/unicorn',
      ),
      'drupalSettings' => [
        'editor' => [
          'formats' => [
            'full_html' => [
              'format'  => 'full_html',
              'editor' => 'unicorn',
              'editorSettings' => $unicorn_plugin->getJSSettings($editor),
              'editorSupportsContentFiltering' => TRUE,
              'isXssSafe' => FALSE,
            ],
          ],
        ],
      ],
    );
    $this->assertIdentical($expected, $this->editorManager->getAttachments(array('filtered_html', 'full_html')), 'Correct attachments when one text editor is enabled and retrieving attachments for multiple text formats.');

    // Case 4: a text editor available associated, but now with its JS settings
    // being altered via hook_editor_js_settings_alter().
    \Drupal::state()->set('editor_test_js_settings_alter_enabled', TRUE);
    $expected['drupalSettings']['editor']['formats']['full_html']['editorSettings']['ponyModeEnabled'] = FALSE;
    $this->assertIdentical($expected, $this->editorManager->getAttachments(array('filtered_html', 'full_html')), 'hook_editor_js_settings_alter() works correctly.');
  }

}
