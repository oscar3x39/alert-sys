## Environment Setup
Ensure PHP and PHP Curl are installed on your system by running the following commands:
```
$ apt install php
$ apt install php-curl
```

## Configuration Settings
Before running the system, make sure to set up the following configurations:

```
LOG_PATH=./logs
SLACK_CHANNEL=XXXXX
STATUS_FILE=./data/status
```

## Cron Configuration
To enable automated system tasks, add the following cron configuration:
```
* * * * * cd /code/alert-sys && /usr/bin/php alertsys.php
```
This cron job will execute the alertsys.php script located in the /code/alert-sys directory every minute. Adjust the paths as necessary based on your system setup.

## License
This project is licensed under the MIT License.
