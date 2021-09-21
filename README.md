# LandoStand Drupal

The LandoStand project is meant to provide a quick and easy development
environment for working on projects locally. The typical process for using Lando
is to fork the project and use it as a base, modifying and adjusting it to meet
your needs.

LandoStand-Drupal ships with a number of utilities that can be accessed and used
inside the containers by running them with the prefix `lando`. For instance:

```
# Install composer dependencies
lando composer install

# Rebuild the Drupal cache with Drush
lando drush cr
```

One of the tools provided in LandoStand Drupal is the Robo task runner for PHP.
The project comes with a variety of example Robo tasks in the `RoboFile.php`
file. With the exception of the `project:init` task, it is highly likely you
will want / need to customize these to suit your needs.

## Local Development

### Dependencies

  - [Docker](https://docs.docker.com/get-docker)
  - [Lando](https://docs.lando.dev/basics/installation.html)

### Installation Steps

  1. Clone the repository
  2. `cd landostand-drupal`
  3. `mkdir config` if you don't already have a config folder
  4. `lando start`
  5. `lando robo project:init`
      - If you receive an error indicating that `robo` cannot be found, run
      `lando composer install` and then re-attempt `lando robo project:init`

### Debugging setup (PHPStorm)
  This is specific to PHPStorm, but the path mapping (step 5) should be useful for vscode or other debuggers. **This project makes use of port 9003 for xdebug connections.**
  1. Open File -> Settings -> PHP -> Settings -> Servers
  2. Click the plus button to add a new server
  3. Name it `appserver` with the host set to `localhost`
  4. Make sure "use path mappings" is checked.
  5. Under the first entry for path mappings, showing this project root:
     - Set `/app` as the "Absolute path on the server"
  6. Close this dialog.
  7. Click the "Start Listening for PHP Debug Connections" button (the phone icon) at the top right

You should now be able to load a page and have the debugger catch requests.

### Making use of phpstan for static analysis

To run `phpstan` use the robo command: `lando robo analyse` which will run `phpstan` on the codebase, excluding common
locations for third party code, and return a list of errors and suggestions for code improvement.

If you are introducing `phpstan` into an existing codebase and initially only want to analyse new code going forward
until technical debt can be addressed, run the `lando robo analyse:baseline` command to record all existing issues into
a `phpstan-baseline.neon` file. Then add this file to the includes section of `phpstan.neon.dist`.

Documentation for `phpstan` can be found at https://phpstan.org/.

### Miscellaneous
  You can define secrets as environment variables with the `.secrets.env` file which is in the `.gitignore`.
