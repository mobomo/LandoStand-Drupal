<?php

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */

use Robo\Tasks;

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
   * Local Site install.
   */
  public function localInstall() {
    $LOCAL_MYSQL_USER = getenv('MYSQL_USER');
    $LOCAL_MYSQL_PASSWORD = getenv('MYSQL_PASSWORD');
    $LOCAL_MYSQL_DATABASE = getenv('MYSQL_DATABASE');
    $LOCAL_MYSQL_PORT = getenv('MYSQL_PORT');
    $this->say("Local site installation started");
    $collection = $this->collectionBuilder();
    $collection->taskComposerInstall()->ignorePlatformRequirements()->noInteraction()
      ->taskExec("drush si -vvv --account-name=admin --account-pass=admin --config-dir=/app/config --db-url=mysql://$LOCAL_MYSQL_USER:$LOCAL_MYSQL_PASSWORD@database:$LOCAL_MYSQL_PORT/$LOCAL_MYSQL_DATABASE -y")
      // ->taskExec('drush cim -vvv -y')
      // ->addTask($this->buildTheme())
      ->taskExec('drush cr');
    $this->say("local site install completed");

    return $collection;
  }

  /**
   * Local Site update.
   */
  public function localUpdate() {
    $this->say("Local site update starting...");
    $collection = $this->collectionBuilder();

    $collection->taskComposerInstall()
      ->taskExec('drush state:set system.maintenance_mode 1 -y')
      ->taskExec('drush updatedb --no-cache-clear -y')
      ->taskExec('drush cim -y || drush cim -y')
      ->taskExec('drush cim -y')
      ->taskExec('drush php-eval "node_access_rebuild();" -y')
      ->addTask($this->buildTheme())
      ->taskExec('drush cr')
      ->taskExec('drush state:set system.maintenance_mode 0 -y')
      ->taskExec('drush cr');
    $this->say("local site Update Completed.");
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
  public function buildTheme($dir = '') {
    if (empty($dir)) {
      $dir = self::CUSTOM_THEMES . '/THEMENAMEHERE';
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
  public function watchTheme() {
    $this->taskGulpRun('watch')->dir(self::CUSTOM_THEMES . '/THEMENAMEHERE')->run();
  }

  /**
   * Update Styles.
   */
  public function updateStyles() {
    $this->taskGulpRun('sass')->dir(self::CUSTOM_THEMES . '/THEMENAMEHERE')->run();
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
    $result = $this->taskExec('vendor/bin/phpcs -ns')
      ->arg('--standard=Drupal,DrupalPractice')
      ->arg('--extensions=php,module,inc,install,test,profile,theme,info')
      ->arg('--ignore=*/node_modules/*')
      ->arg(self::CUSTOM_MODULES)
      ->arg(self::CUSTOM_THEMES)
      ->printOutput(TRUE)
      ->run();
    $message = $result->wasSuccessful() ? 'No Drupal standards violations found :)' : 'Drupal standards violations found :( Please review the code.';
    $this->say("php code sniffer finished: " . $message);
  }

  /**
   * Runs Beautifier.
   */
  public function codefix() {
    $this->say("php code beautifier (drupalStandards) started...");
    $this->taskExec('vendor/bin/phpcbf')
      ->arg('--standard=Drupal')
      ->arg('--extensions=php,module,inc,install,test,profile,theme,info')
      ->arg(self::CUSTOM_MODULES)
      ->arg(self::CUSTOM_THEMES)
      ->printOutput(TRUE)
      ->run();
    $this->say("php code beautifier finished.");
  }

  /**
   * Builds Docker env.
   */
  public function build()  {
    $this->say("Spinning up docker containers ᕕ( ᐛ )ᕗ");
    $this->stopOnFail(true);
    // Exec docker-compose to spin up containers.
    $build = $this->collectionBuilder();
    $build->taskExec('docker-compose up -d --build')
      ->taskExec('docker-compose ps');

    return $build;
  }

  /**
   * Destroys everything!
   * Stops and removes docker-compose containers and volumes.
   */
  public function destroy()  {
    $this->say("Stopping and removing containers ( ' ')ﾉﾉ⌒○~*");
    $this->stopOnFail(true);
    // Exec docker-compose to spin up containers.
    $this->taskExec('docker-compose down -v')
      ->run();
  }

  /********************************************************
   * FOR REMOTE TESTING WE RUN ROBO INSIDE THE PHP CONTAINER! *
   ********************************************************/
  /**
   * Remote Site install.
   */
  public function remoteInstall() {
    $this->say("Remote site installation started. ");
    $this->stopOnFail();
    // Append service.
    $container_php = getenv('PROJECT') . "-php";
    // Composer install.
    $this->taskDockerExec($container_php)
      ->exec($this->taskComposerInstall('/usr/local/bin/composer')
        ->ignorePlatformRequirements()
        ->noInteraction()
        ->optimizeAutoloader()
        ->arg('--no-progress')
      )
      ->run();
    // Wait for the DB container to be up.
    $this->taskDockerExec($container_php)
      ->exec($this->taskExec('sh -c "while ! nc -w 2 -z $DRUPAL_MYSQL_HOST 3306; do sleep 1; done"'))
      ->run();
    // Drupal install.
    $this->taskDockerExec($container_php)
      ->exec($this->taskExec($this->drupalInstall()))
      ->run();
    // Import config.
    $this->taskDockerExec($container_php)
      ->exec($this->drushCim())
      ->run();
    // Install theme. Oh yes, no way to exec a collection inside a container \o/.
    $this->taskDockerExec($container_php)
      ->exec('bash -c "/usr/bin/npm install --prefix /var/www/webroot/themes/custom/THEMENAMEHERE && cd /var/www/webroot/themes/custom/THEMENAMEHERE && gulp"')
      ->run();
    // Clear Cache.
    $this->taskDockerExec($container_php)
      ->exec($this->drush()
        ->arg('cr'))
      ->run();
    // Fix perms.
    $this->taskDockerExec($container_php)
      ->exec($this->fixPerms())
      ->run();
  }

  /**
   * Drush Drupal install.
   *
   * @return \Robo\Task\Base\Exec
   *   Drupal install from existing config.
   */
  protected function drupalInstall() {
    // @todo: Do we need to set files public access?
    // sh -c "chmod 777 sites/default/files"
    return $this->drush()
      ->arg('site-install')
      ->arg('--account-name=admin')
      ->arg('--account-pass=admin')
      ->arg('--existing-config')
      ->option('yes');
  }

  /**
   * Drush Config Import.
   *
   * @return \Robo\Task\Base\Exec
   *   Import config. Running it three times brings prosperity and good luck :p
   */
  protected function drushCim() {
    return $this->drush()
      ->arg('config-import')
      ->option('yes');
  }

  /**
   * Runs Codesniffer on remote env.
   */
  public function remotePhpcs() {
    $this->say("php code sniffer (drupalStandards) started...");
    $this->stopOnFail(TRUE);
    $container_php = getenv('PROJECT') . "-php";
    // I love stupid dependencies.
    $this->taskDockerExec($container_php)
      ->exec('sh -c "vendor/bin/phpcs --config-set installed_paths vendor/drupal/coder/coder_sniffer"')
      ->run();

    $result = $this->taskDockerExec($container_php)
      ->exec(
        $this->taskExec('vendor/bin/phpcs -ns')
          ->arg('--standard=Drupal,DrupalPractice')
          ->arg('--extensions=php,module,inc,install,test,profile,theme,info')
          ->arg('--ignore=*/node_modules/*')
          ->arg('webroot/modules/custom')
          ->arg('webroot/themes/custom')
          ->printOutput(FALSE)
      )
      ->run();
    if ($result->wasSuccessful()) {
      $this->say('Wohooo! Nice looking php code ᕕ(⌐■_■)ᕗ ♪♬');
    }
    else {
      $this->say('Oh, dear! There were errors... check them above ^ (╥﹏╥)');
    }
  }

  /**
   * Default drush command with root set.
   *
   * @return \Robo\Task\Base\Exec
   *   Exec drush.
   */
  protected function drush() {
    return $this->taskExec('drush')
      ->option('--root=/var/www/webroot');
  }

  /**
   * Fixes permissions on the host using alpine image.
   *
   * @return Robo\Collection\CollectionBuilder|Robo\Task\Docker\Run
   *
   */
  public function hostPerms () {
    $this->say("Fixing permissions on host...");
    return $this->taskDockerRun('alpine:latest')
      ->volume(__DIR__, '/mnt')
      ->containerWorkdir('/mnt')
      ->exec($this->fixPerms())
      ->option('rm');
  }

  /**
   * Fixes files permissions.
   *
   * @return \Robo\Collection\CollectionBuilder|\Robo\Task\Base\ExecStack
   *   Exec chown and chmod.
   */
  public function fixPerms() {
    return $this->taskExecStack()
      ->stopOnFail()
      ->exec('chown $(id -u) ./')
      ->exec('chmod u=rwx,g=rwxs,o=rx ./')
      ->exec('find ./ -not -path "webroot/sites/default/files*" -exec chown $(id -u) {} \;')
      ->exec('find ./ -not -path "webroot/sites/default/files*" -exec chmod u=rwX,g=rwX,o=rX {} \;')
      ->exec('find ./ -type d -not -path "webroot/sites/default/files*" -exec chmod g+s {} \;')
      ->exec('chmod -R u=rwx,g=rwxs,o=rwx ./webroot/sites/default/files');
  }

  /**
   * Remote run composer install in container.
   *
   * @return \Robo\Collection\CollectionBuilder|\Robo\Task\Docker\Run
   */
  public function remoteComposer() {
    $this->say("Composer install on docker container...");
    return $this->taskDockerRun('composer')
      ->user(exec('id -u') . ':' . exec('id -g'))
      ->volume(__DIR__ , '/app')
      ->volume('composer-cache', '/tmp/cache')
      ->option('rm')
      ->exec('install --ignore-platform-reqs --no-interaction --no-progress --optimize-autoloader');
  }

  /**
   * Remote Site update.
   *
   * @return \Robo\Collection\CollectionBuilder
   */
  public function remoteUpdate() {
    $this->say("Dev site update starting...");
    $collection = $this->collectionBuilder();
    $collection->progressMessage('Setting maintenance mode ON')
      ->taskExec('drush state:set system.maintenance_mode 1 -y')
      ->progressMessage('Updating the database...(without clearing cache)')
      ->taskExec('drush updatedb --no-cache-clear -y')
      ->progressMessage('Importing the Config...(twice)')
      ->taskExec('drush cim -y || drush cim -y')
      ->taskExec('drush cim -y')
      ->progressMessage('Node access rebuild...')
      ->taskExec('drush php-eval "node_access_rebuild();" -y')
      ->progressMessage('Build theme...')
      ->addTask($this->buildTheme())
      ->progressMessage('CLear Cache...')
      ->taskExec('drush cr')
      ->progressMessage('Unset maintenance mode')
      ->taskExec('drush state:set system.maintenance_mode 0 -y')
      ->progressMessage('Clear Cache...')
      ->taskExec('drush cr');

    return $collection;
  }

  /**
   * Remote deployment.
   *
   * @return \Robo\Result
   *   Te result of tasks collection.
   */
  public function remoteRelease() {
    $collection = $this->collectionBuilder();
    $collection->addTask($this->remoteComposer());
    $collection->taskExec("cp ../docroot/webroot/sites/default/settings.php webroot/sites/default/settings.php");
    $collection->addTask($this->remoteUpdate());
    $collection->addTask($this->hostPerms());

    return $collection->run();
  }

  /**
   * Git tasks.
   *
   * @return \Robo\Collection\CollectionBuilder|\Robo\Task\Vcs\GitStack
   */
  public function gitClean() {
    $this->yell("Cleaning dir for git files...");
    return $this->taskGitStack()
      ->checkout('-- .')
      ->exec('clean -d -f');
  }

  /**
   * Git tasks.
   *
   * @param string $tag
   *
   * @return \Robo\Collection\CollectionBuilder|\Robo\Task\Vcs\GitStack
   */
  public function gitCheckout($tag) {
    $this->yell("Checking out released tag: $tag");
    return $this->taskGitStack()
      ->exec('fetch')
      ->checkout($tag)
      ->exec('status --short');
  }

  /**
   * Cat current checked out tag to a file.
   *
   * @param string $tag
   */
  public function initDtag(string $tag = NULL) {
    $deployment_id_file = __DIR__ . '/deployment_identifier';
    $this->say("Printing Deployment Identifier to file...");

    if (!$tag) {
      $get_tag = $this->taskExec('git describe --abbrev=0 --tags')
        ->printOutput(FALSE)
        ->interactive(FALSE)
        ->run();
      $tag = $get_tag->getMessage();
      // $tag = exec('git describe --abbrev=0 --tags');
      if ($get_tag->getExitCode() > 0) {
        $this->yell("Unable to find a git repository :( Deployment Identifier will not be set.");
      }
      else {
        $result = $this->taskWriteToFile($deployment_id_file)
          ->progressMessage("Printed tag: $tag OK")
          ->line($tag)
          ->run();
      }
    }
    // If a tag is manually passed. Set it directly. Odd case, we could remove it?
    else {
      $result = $this->taskWriteToFile($deployment_id_file)
        ->line($tag)
        ->run();
    }
  }

  /**
   * Set/Unset maintenance mode.
   *
   * @param int $status
   *
   * @return \Robo\Collection\CollectionBuilder|\Robo\Task\Base\ExecStack
   */
  public function maintenanceMode(int $status) {
    return $this->taskExecStack()
      ->stopOnFail()
      ->exec("drush state:set system.maintenance_mode $status")
      ->exec("drush cr");
  }
}
