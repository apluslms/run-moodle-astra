# Moodle Astra container for local development and testing

This is a container for running [Moodle](https://moodle.org/) with the
[Astra](https://github.com/Aalto-LeTech/moodle-mod_astra/) plugin.
It serves as a course frontend that retrieves course contents from
the [MOOC grader](https://github.com/Aalto-LeTech/mooc-grader) and
sends submissions there for grading.

`Dockerfile` is used to build the Moodle container. The built image has been
pushed to Docker Hub so that you normally do not need to build it manually.
The container is based on the [moodle-docker](https://github.com/moodlehq/moodle-docker)
repository and the [moodle-php-apache](https://github.com/moodlehq/moodle-php-apache) container
from Moodle HQ.

## Running a MOOC grader course with this container

Copy `docker-moodle-up.sh` and `docker-compose-moodle.yml` into the MOOC grader
course directory (such as the [template course](https://github.com/apluslms/course-templates)).
The container is then started with `docker-moodle-up.sh`. The compose file
mounts the course directory into the MOOC grader.

When the container is started for the first time, Moodle installs
the database tables, which can take a couple of minutes of time.
If the containers are stopped without destroying the data volumes
(by using the ESC key with docker-moodle-up.sh), the database does
not need to be installed again when the containers are started again
at another time.

The Moodle container has a default administrator user with
username `admin` and password `admin`. Additionally, users `teacher`,
`student`, and `assistant` are also added automatically with passwords
matching the username.


## Moodle/Astra developers: running Astra unit tests

Copy `docker-compose-moodle.yml`, `docker-compose-moodle-phpunit.yml`, and
`docker-moodle-up.sh` to the MOOC grader course directory. Then run the command
`./docker-moodle-up.sh unittest` in the course directory.

