# Using commands directly

Redis has lots of useful commands which could be used directly from connection. After configuring application as
shown in [Configuring application](../../../README.md#Configuring-application) section, connection could be obtained like the following:

```php
$redis = Yii::$app->redis;
```

After it's done one can execute commands. The most generic way to do it is using `executeCommand` method:

```php
$result = $redis->executeCommand('hmset', ['test_collection', 'key1', 'val1', 'key2', 'val2']);
```

There are shortcuts available for each command supported so insted of the above it can be used as follows:

```php
$result = $redis->hmset('test_collection', 'key1', 'val1', 'key2', 'val2');
```

For a list of available commands and their parameters see [redis Commands](https://redis.io/commands).
