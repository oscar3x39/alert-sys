## Environments
```
apt install php
apt install php-curl
```

```
LOG_PATH=./logs
SLACK_CHANNEL=XXXXX
STATUS_FILE=./data/status
```

## Only need to Add cron configuartion

```
* * * * * cd /code/alert-sys && /usr/bin/php alertsys.php
```
