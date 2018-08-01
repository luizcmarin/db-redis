<?php

namespace yii\db\redis\tests;

use yii\exceptions\InvalidConfigException;
use yii\db\redis\Cache;
use yii\db\redis\Connection;
use yii\cache\tests\unit\CacheTestCase;

/**
 * Class for testing redis cache backend
 * @group redis
 * @group cache
 */
class RedisCacheTest extends CacheTestCase
{
    private $_cacheInstance;

    /**
     * @return Cache
     */
    protected function getCacheInstance()
    {
        $databases = TestCase::getParam('databases');
        $params = isset($databases['redis']) ? $databases['redis'] : null;
        if ($params === null) {
            $this->markTestSkipped('No redis server connection configured.');
        }
        $connection = new Connection($params);
//        if (!@stream_socket_client($connection->hostname . ':' . $connection->port, $errorNumber, $errorDescription, 0.5)) {
//            $this->markTestSkipped('No redis server running at ' . $connection->hostname . ':' . $connection->port . ' : ' . $errorNumber . ' - ' . $errorDescription);
//        }

        $this->container->set('redis', $connection);
        $this->mockApplication(); //['components' => ['redis' => $connection]]);

        if ($this->_cacheInstance === null) {
            $this->_cacheInstance = new Cache($connection);
        }

        return $this->_cacheInstance;
    }

    protected function resetCacheInstance()
    {
        $this->getCacheInstance()->clear();
        $this->_cacheInstance = null;
    }

    public function testExpireMilliseconds()
    {
        $cache = $this->getCacheInstance();

        $this->assertTrue($cache->set('expire_test_ms', 'expire_test_ms', 0.2));
        usleep(100000);
        $this->assertEquals('expire_test_ms', $cache->get('expire_test_ms'));
        usleep(300000);
        $this->assertFalse($cache->get('expire_test_ms'));
    }

    public function testExpireAddMilliseconds()
    {
        $cache = $this->getCacheInstance();

        $this->assertTrue($cache->add('expire_testa_ms', 'expire_testa_ms', 0.2));
        usleep(100000);
        $this->assertEquals('expire_testa_ms', $cache->get('expire_testa_ms'));
        usleep(300000);
        $this->assertFalse($cache->get('expire_testa_ms'));
    }

    /**
     * Store a value that is 2 times buffer size big
     * https://github.com/yiisoft/yii2/issues/743
     */
    public function testLargeData()
    {
        $cache = $this->getCacheInstance();

        $data = str_repeat('XX', 8192); // http://www.php.net/manual/en/function.fread.php
        $key = 'bigdata1';

        $this->assertFalse($cache->get($key));
        $cache->set($key, $data);
        $this->assertSame($cache->get($key), $data);

        // try with multibyte string
        $data = str_repeat('ЖЫ', 8192); // http://www.php.net/manual/en/function.fread.php
        $key = 'bigdata2';

        $this->assertFalse($cache->get($key));
        $cache->set($key, $data);
        $this->assertSame($cache->get($key), $data);
    }

    /**
     * Store a megabyte and see how it goes
     * https://github.com/yiisoft/yii2/issues/6547
     */
    public function testReallyLargeData()
    {
        $cache = $this->getCacheInstance();

        $keys = [];
        for ($i = 1; $i < 16; $i++) {
            $key = 'realbigdata' . $i;
            $data = str_repeat('X', 100 * 1024); // 100 KB
            $keys[$key] = $data;

//            $this->assertTrue($cache->get($key) === false); // do not display 100KB in terminal if this fails :)
            $cache->set($key, $data);
        }
        $values = $cache->getMultiple(array_keys($keys));
        foreach ($keys as $key => $value) {
            $this->assertArrayHasKey($key, $values);
            $this->assertSame($values[$key], $value);
        }
    }

    public function testMultiByteGetAndSet()
    {
        $cache = $this->getCacheInstance();

        $data = ['abc' => 'ежик', 2 => 'def'];
        $key = 'data1';

        $this->assertFalse($cache->get($key));
        $cache->set($key, $data);
        $this->assertSame($cache->get($key), $data);
    }

    public function testReplica()
    {
        $this->resetCacheInstance();

        $cache = $this->getCacheInstance();
        $cache->enableReplicas = true;

        $key = 'replica-1';
        $value = 'replica';

        //No Replica listed
        $this->assertFalse($cache->get($key));
        $cache->set($key, $value);
        $this->assertSame($cache->get($key), $value);

        $cache->replicas = [
            ['hostname' => 'localhost'],
        ];
        $this->assertSame($cache->get($key), $value);

        //One Replica listed
        $this->resetCacheInstance();
        $cache = $this->getCacheInstance();
        $cache->enableReplicas = true;
        $cache->replicas = [
            ['hostname' => 'localhost'],
        ];
        $this->assertFalse($cache->get($key));
        $cache->set($key, $value);
        $this->assertSame($cache->get($key), $value);

        //Multiple Replicas listed
        $this->resetCacheInstance();
        $cache = $this->getCacheInstance();
        $cache->enableReplicas = true;

        $cache->replicas = [
            ['hostname' => 'localhost'],
            ['hostname' => 'localhost'],
        ];
        $this->assertFalse($cache->get($key));
        $cache->set($key, $value);
        $this->assertSame($cache->get($key), $value);

        //invalid config
        $this->resetCacheInstance();
        $cache = $this->getCacheInstance();
        $cache->enableReplicas = true;

        $cache->replicas = ['redis'];
        $this->assertFalse($cache->get($key));
        $cache->set($key, $value);
        $this->assertSame($cache->get($key), $value);

        $this->resetCacheInstance();
    }
}
