<?php

/**
 * @file
 * Contains \Drupal\node\Tests\NodeCreationTest.
 */

namespace Drupal\node\Tests;

use Drupal\Core\Database\Database;
use Drupal\Core\Language\LanguageInterface;

/**
 * Create a node and test saving it.
 *
 * @group node
 */
class NodeCreationTest extends NodeTestBase {

  /**
   * Modules to enable.
   *
   * Enable dummy module that implements hook_ENTITY_TYPE_insert() for
   * exceptions (function node_test_exception_node_insert() ).
   *
   * @var array
   */
  public static $modules = array('node_test_exception', 'dblog', 'test_page_test');

  protected function setUp() {
    parent::setUp();

    $web_user = $this->drupalCreateUser(array('create page content', 'edit own page content'));
    $this->drupalLogin($web_user);
  }

  /**
   * Creates a "Basic page" node and verifies its consistency in the database.
   */
  function testNodeCreation() {
    $node_type_storage = \Drupal::entityManager()->getStorage('node_type');

    // Test /node/add page with only one content type.
    $node_type_storage->load('article')->delete();
    $this->drupalGet('node/add');
    $this->assertResponse(200);
    $this->assertUrl('node/add/page');
    // Create a node.
    $edit = array();
    $edit['title[0][value]'] = $this->randomMachineName(8);
    $edit['body[0][value]'] = $this->randomMachineName(16);
    $this->drupalPostForm('node/add/page', $edit, t('Save'));

    // Check that the Basic page has been created.
    $this->assertRaw(t('!post %title has been created.', array('!post' => 'Basic page', '%title' => $edit['title[0][value]'])), 'Basic page created.');

    // Check that the node exists in the database.
    $node = $this->drupalGetNodeByTitle($edit['title[0][value]']);
    $this->assertTrue($node, 'Node found in database.');

    // Verify that pages do not show submitted information by default.
    $this->drupalGet('node/' . $node->id());
    $this->assertNoText($node->getOwner()->getUsername());
    $this->assertNoText(format_date($node->getCreatedTime()));

    // Change the node type setting to show submitted by information.
    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = $node_type_storage->load('page');
    $node_type->setDisplaySubmitted(TRUE);
    $node_type->save();

    $this->drupalGet('node/' . $node->id());
    $this->assertText($node->getOwner()->getUsername());
    $this->assertText(format_date($node->getCreatedTime()));
  }

  /**
   * Verifies that a transaction rolls back the failed creation.
   */
  function testFailedPageCreation() {
    // Create a node.
    $edit = array(
      'uid'      => $this->loggedInUser->id(),
      'name'     => $this->loggedInUser->name,
      'type'     => 'page',
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      'title'    => 'testing_transaction_exception',
    );

    try {
      // An exception is generated by node_test_exception_node_insert() if the
      // title is 'testing_transaction_exception'.
      entity_create('node', $edit)->save();
      $this->fail(t('Expected exception has not been thrown.'));
    }
    catch (\Exception $e) {
      $this->pass(t('Expected exception has been thrown.'));
    }

    if (Database::getConnection()->supportsTransactions()) {
      // Check that the node does not exist in the database.
      $node = $this->drupalGetNodeByTitle($edit['title']);
      $this->assertFalse($node, 'Transactions supported, and node not found in database.');
    }
    else {
      // Check that the node exists in the database.
      $node = $this->drupalGetNodeByTitle($edit['title']);
      $this->assertTrue($node, 'Transactions not supported, and node found in database.');

      // Check that the failed rollback was logged.
      $records = static::getWatchdogIdsForFailedExplicitRollback();
      $this->assertTrue(count($records) > 0, 'Transactions not supported, and rollback error logged to watchdog.');
    }

    // Check that the rollback error was logged.
    $records = static::getWatchdogIdsForTestExceptionRollback();
    $this->assertTrue(count($records) > 0, 'Rollback explanatory error logged to watchdog.');
  }

  /**
   * Creates an unpublished node and confirms correct redirect behavior.
   */
  function testUnpublishedNodeCreation() {
    // Set the front page to the test page.
    $this->config('system.site')->set('page.front', '/test-page')->save();

    // Set "Basic page" content type to be unpublished by default.
    $fields = \Drupal::entityManager()->getFieldDefinitions('node', 'page');
    $fields['status']->getConfig('page')
      ->setDefaultValue(FALSE)
      ->save();

    // Create a node.
    $edit = array();
    $edit['title[0][value]'] = $this->randomMachineName(8);
    $edit['body[0][value]'] = $this->randomMachineName(16);
    $this->drupalPostForm('node/add/page', $edit, t('Save'));

    // Check that the user was redirected to the home page.
    $this->assertUrl('');
    $this->assertText(t('Test page text'));

    // Confirm that the node was created.
    $this->assertRaw(t('!post %title has been created.', array('!post' => 'Basic page', '%title' => $edit['title[0][value]'])));
  }

  /**
   * Tests the author autocompletion textfield.
   */
  public function testAuthorAutocomplete() {
    $admin_user = $this->drupalCreateUser(array('administer nodes', 'create page content'));
    $this->drupalLogin($admin_user);

    $this->drupalGet('node/add/page');

    $result = $this->xpath('//input[@id="edit-uid-0-value" and contains(@data-autocomplete-path, "user/autocomplete")]');
    $this->assertEqual(count($result), 0, 'No autocompletion without access user profiles.');

    $admin_user = $this->drupalCreateUser(array('administer nodes', 'create page content', 'access user profiles'));
    $this->drupalLogin($admin_user);

    $this->drupalGet('node/add/page');

    $result = $this->xpath('//input[@id="edit-uid-0-target-id" and contains(@data-autocomplete-path, "/entity_reference_autocomplete/user/default")]');
    $this->assertEqual(count($result), 1, 'Ensure that the user does have access to the autocompletion');
  }

  /**
   * Check node/add when no node types exist.
   */
  function testNodeAddWithoutContentTypes () {
    $this->drupalGet('node/add');
    $this->assertResponse(200);
    $this->assertNoLinkByHref('/admin/structure/types/add');

    // Test /node/add page without content types.
    foreach (\Drupal::entityManager()->getStorage('node_type')->loadMultiple() as $entity ) {
      $entity->delete();
    }

    $this->drupalGet('node/add');
    $this->assertResponse(403);

    $admin_content_types = $this->drupalCreateUser(array('administer content types'));
    $this->drupalLogin($admin_content_types);

    $this->drupalGet('node/add');

    $this->assertLinkByHref('/admin/structure/types/add');
  }

  /**
   * Gets the watchdog IDs of the records with the rollback exception message.
   *
   * @return int[]
   *   Array containing the IDs of the log records with the rollback exception
   *   message.
   */
  protected static function getWatchdogIdsForTestExceptionRollback() {
    // PostgreSQL doesn't support bytea LIKE queries, so we need to unserialize
    // first to check for the rollback exception message.
    $matches = array();
    $query = db_query("SELECT wid, variables FROM {watchdog}");
    foreach ($query as $row) {
      $variables = (array) unserialize($row->variables);
      if (isset($variables['@message']) && $variables['@message'] === 'Test exception for rollback.') {
        $matches[] = $row->wid;
      }
    }
    return $matches;
  }

  /**
   * Gets the log records with the explicit rollback failed exception message.
   *
   * @return \Drupal\Core\Database\StatementInterface
   *   A prepared statement object (already executed), which contains the log
   *   records with the explicit rollback failed exception message.
   */
  protected static function getWatchdogIdsForFailedExplicitRollback() {
    return db_query("SELECT wid FROM {watchdog} WHERE message LIKE 'Explicit rollback failed%'")->fetchAll();
  }

}
