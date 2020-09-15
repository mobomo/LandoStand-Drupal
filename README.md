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
  3. `lando start`
  4. `lando robo project:init`
      - If you receive an error indicating that `robo` cannot be found, run
      `lando composer install` and then re-attempt `lando robo project:init`

