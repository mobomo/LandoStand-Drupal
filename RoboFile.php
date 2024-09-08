<?php

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */

use Robo\Tasks;
use Robo\Collection\CollectionBuilder;
use Robo\Task\Base\ExecStack;

/**
 * Robo Tasks.
 */
class RoboFile extends Tasks {
  /**
   * The path to custom modules.
   *
   * @var string
   */
  const CUSTOM_MODULES = __DIR__ . '/webroot/modules/custom';

  /**
   * The path to custom themes.
   *
   * @var string
   */
  const CUSTOM_THEMES = __DIR__ . '/webroot/themes/custom';

  /**
   * The default admin theme during site-install phase.
   *
   * @var string
   */
  const DEFAULT_ADMIN_THEME = 'claro';
  
  /**
   * The default theme during site-install phase.
   *
   * @var string
   */
  const DEFAULT_THEME = 'uswds_base_subtheme';
  
  /**
   * The loaded relevant environment variables.
   */
  private $env;

  public function __construct() {
    $this->env = new RoboEnvironment();
  }

  public function projectInit() {

    $defaultTheme = $this->_getDefaultTheme();
    $adminTheme = $this->_getAdminTheme();

    //$password = generateStrongPassword();
    $password = 'admin';

    $collection = $this->collectionBuilder();
    $collection->taskComposerInstall()
      ->ignorePlatformRequirements()
      ->noInteraction()
      ->addCode(function() {

        if (file_exists('salt.txt')) {
          return 0; // Return 0 to indicate success
        }

        $result = $this->taskExec('drush eval "echo Drupal\Component\Utility\Crypt::randomBytesBase64(55)"')
          ->silent(TRUE)
          ->run();
        
        if ($result->wasSuccessful()) {
          $salt = trim($result->getMessage());
          file_put_contents('salt.txt', $salt);
          $this->say("Salt generated and saved to salt.txt");
        } else {
          $this->say("Failed to generate salt");
        }
        
        return 0; // Return 0 to indicate success
      })
      ->taskExec("drush si --account-name=admin --account-pass=\"$password\" --config-dir={$this->env->LOCAL_CONFIG_DIR} --db-url={$this->env->DB_URL} minimal -y")
      ->taskExec("drush pm:enable shortcut toolbar admin_toolbar -y")
      ->taskExec('drush cr')
      ->taskExec("drush theme:enable $defaultTheme $adminTheme -y")
      ->taskExec("drush config:set system.theme default $defaultTheme -y")
      ->taskExec("drush config:set system.theme admin $adminTheme -y")
      ->taskExec("drush config:set node.settings use_admin_theme true -y")
      ->taskExec('drush cr')
      ->taskExec($this->fixPerms())
      ->addCode(function() use ($password) {
        echo "\n\n====CREDENTIALS====
Username: admin
Password: $password
===================\n\n";
        return 0; // Return 0 to indicate success
      });

    return $collection;
  }

  /**
   * Build theme.
   *
   * @param string $dir
   *  The directory to run the commands.
   *
   * @return \Robo\Collection\CollectionBuilder
   */
  public function themeBuild($dir = '') {
    if (empty($dir)) {
      $dir = self::CUSTOM_THEMES . '/' . $this->_getDefaultTheme();
    }
    $collection = $this->collectionBuilder();
    $collection->progressMessage('Building the theme...')
      ->taskNpmInstall()->dir($dir)
      ->taskGulpRun('default')->dir($dir);

    return $collection;
  }

  /**
   * Watch theme.
   */
  public function themeWatch() {
    $this->taskGulpRun('watch')->dir(self::CUSTOM_THEMES . '/' . $this->_getDefaultTheme())->run();
  }

  /**
   * Update Styles.
   */
  public function themeUpdate() {
    $this->taskGulpRun('sass')->dir(self::CUSTOM_THEMES . '/' . $this->_getDefaultTheme())->run();
    $this->taskExec('drush cc css-js')->run();
  }

  /**
   * Lint.
   */
  public function lint() {
    $this->say("parallel-lint checking custom modules and themes...");
    $this->taskExec('vendor/bin/parallel-lint -e php,module,inc,install,test,profile,theme')
      ->arg(self::CUSTOM_MODULES)
      ->arg(self::CUSTOM_THEMES)
      ->printOutput(TRUE)
      ->run();
    $this->say("parallel-lint finished.");
  }

  /**
   * Runs Codesniffer.
   */
  public function phpcs() {
    $this->say("php code sniffer (drupalStandards) started...");
    $result = $this->taskExec('./vendor/bin/phpcs')
      ->arg('-ns')
      ->printOutput(TRUE)
      ->run();
    $message = $result->wasSuccessful() ? 'No Drupal standards violations found :)' : 'Drupal standards violations found :( Please review the code.';
    $this->say("php code sniffer finished: " . $message);
  }

  /**
   * Runs phpstan.
   */
  public function analyse() {
    $this->say("Running Static Code Analysis...");
    $result = $this->taskExec('vendor/bin/phpstan')
      ->arg('analyse')
      ->printOutput(TRUE)
      ->run();
    $this->say("Complete.");
  }

  /**
   * Records phpstan baseline.
   */
  public function analyseBaseline() {

    if (file_exists('/app/phpstan-baseline.neon')) {
      $continue = $this->confirm("This will update an existing baseline, are you sure?", FALSE);
    }
    else {
      $continue = TRUE;
    }

    if ($continue) {
      $this->say("Establishing Static Code Analysis Baseline...");
      $result = $this->taskExec('vendor/bin/phpstan')
        ->arg('analyse')
        ->arg('--generate-baseline')
        ->printOutput(TRUE)
        ->run();

      if ($result->wasSuccessful()) {
        $this->io()->success('Ensure that phpstan-baseline.neon is added to the includes section of phpstan.neon or phpstan.neon.dist configuration file.');
      }
    }
    $this->say("Complete.");
  }

  /**
   * Runs Beautifier.
   */
  public function codefix() {
    $this->say("PHP Code Beautifier (drupalStandards) started...");
    $this->taskExec('vendor/bin/phpcbf')
      ->arg('--standard=Drupal')
      ->arg('--extensions=php,module,inc,install,test,profile,theme,info')
      ->arg(self::CUSTOM_MODULES)
      ->arg(self::CUSTOM_THEMES)
      ->printOutput(TRUE)
      ->run();
    $this->say("PHP Code Beautifier finished.");
  }

  /**
   * Fixes files permissions.
   *
   * @return \Robo\Collection\CollectionBuilder|\Robo\Task\Base\ExecStack
   *   Exec chown and chmod.
   */
  public function fixPerms(): CollectionBuilder|ExecStack {
    return $this->taskExecStack()
      ->stopOnFail()
      ->exec('chown $(id -u) ./')
      ->exec('chmod u=rwx,g=rwxs,o=rx ./')
      ->exec('find ./ -not -path "webroot/sites/default/files*" -exec chown $(id -u) {} \;')
      ->exec('find ./ -not -path "webroot/sites/default/files*" -exec chmod u=rwX,g=rwX,o=rX {} \;')
      ->exec('find ./ -type d -not -path "webroot/sites/default/files*" -exec chmod g+s {} \;')
      ->exec('chmod -R u=rwx,g=rwxs,o=rwx ./webroot/sites/default/files');
  }

  public function _getDefaultTheme(): string {
    $result = $this
      ->taskExec('drush config:get system.theme default')
      ->silent(TRUE)
      ->run();
    
    if ($result->wasSuccessful()) {
      $theme = str_replace("'system.theme:default': ", '', $result->getMessage());
      if (empty($theme) || $theme == 'stark') {
        return self::DEFAULT_THEME;
      }
      return $theme;
    }
    return self::DEFAULT_THEME;
  }

  public function _getAdminTheme(): string {
    $result = $this
      ->taskExec('drush config:get system.theme admin')
      ->silent(TRUE)
      ->run();
    
    if ($result->wasSuccessful()) {
      $theme = str_replace("'system.theme:admin': ", '', $result->getMessage());

      if (empty($theme) || $theme == 'stark') {
        return self::DEFAULT_ADMIN_THEME;
      }
      return $theme;
    }
    return self::DEFAULT_ADMIN_THEME;
  }
}

Class RoboEnvironment {
  const CUSTOM_MODULES = __DIR__ . '/webroot/modules/custom';
  const CUSTOM_THEMES = __DIR__ . '/webroot/themes/custom';

  public string $LOCAL_MYSQL_HOST;
  public string $LOCAL_MYSQL_USER;
  public string $LOCAL_MYSQL_PASSWORD;
  public string $LOCAL_MYSQL_DATABASE ;
  public string $LOCAL_MYSQL_PORT;
  public string $LOCAL_CONFIG_DIR;
  public string $DB_URL;

  public function __construct() {
    $this->LOCAL_MYSQL_HOST = getenv('DRUPAL_DB_HOST');
    $this->LOCAL_MYSQL_USER = getenv('DRUPAL_DB_USER');
    $this->LOCAL_MYSQL_PASSWORD = getenv('DRUPAL_DB_PASS');
    $this->LOCAL_MYSQL_DATABASE = getenv('DRUPAL_DB_NAME');
    $this->LOCAL_MYSQL_PORT = getenv('DRUPAL_DB_PORT');
    $this->LOCAL_CONFIG_DIR = getenv('DRUPAL_CONFIG_DIR');
    $this->DB_URL = "mysql://{$this->LOCAL_MYSQL_USER}:{$this->LOCAL_MYSQL_PASSWORD}@{$this->LOCAL_MYSQL_HOST}:{$this->LOCAL_MYSQL_PORT}/{$this->LOCAL_MYSQL_DATABASE}";
  }
}

/** Utility **/

// Generates a strong password of N length containing at least one lower case letter,
// one uppercase letter, one digit, and one special character. The remaining characters
// in the password are chosen at random from those four sets.
//
// The available characters in each set are user friendly - there are no ambiguous
// characters such as i, l, 1, o, 0, etc. This, coupled with the $add_dashes option,
// makes it much easier for users to manually type or speak their passwords.
//
// Note: the $add_dashes option will increase the length of the password by
// floor(sqrt(N)) characters.

function generateStrongPassword($length = 15, $add_dashes = false, $available_sets = 'luds')
{
	$sets = array();
	if(strpos($available_sets, 'l') !== false)
		$sets[] = 'abcdefghjkmnpqrstuvwxyz';
	if(strpos($available_sets, 'u') !== false)
		$sets[] = 'ABCDEFGHJKMNPQRSTUVWXYZ';
	if(strpos($available_sets, 'd') !== false)
		$sets[] = '23456789';
	if(strpos($available_sets, 's') !== false)
		$sets[] = '!@#$%&*?';

	$all = '';
	$password = '';
	foreach($sets as $set)
	{
		$password .= $set[tweak_array_rand(str_split($set))];
		$all .= $set;
	}

	$all = str_split($all);
	for($i = 0; $i < $length - count($sets); $i++)
		$password .= $all[tweak_array_rand($all)];

	$password = str_shuffle($password);

	if(!$add_dashes)
		return $password;

	$dash_len = floor(sqrt($length));
	$dash_str = '';
	while(strlen($password) > $dash_len)
	{
		$dash_str .= substr($password, 0, $dash_len) . '-';
		$password = substr($password, $dash_len);
	}
	$dash_str .= $password;
	return $dash_str;
}
//take a array and get random index, same function of array_rand, only diference is
// intent use secure random algoritn on fail use mersene twistter, and on fail use defaul array_rand
function tweak_array_rand($array){
	if (function_exists('random_int')) {
		return random_int(0, count($array) - 1);
	} elseif(function_exists('mt_rand')) {
		return mt_rand(0, count($array) - 1);
	} else {
		return array_rand($array);
	}
}

