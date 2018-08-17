<?php

namespace ForFit\Mongodb\Cache;

use Illuminate\Cache\DatabaseStore;
use Closure;
use MongoDB\BSON\UTCDateTime;

class Store extends DatabaseStore
{

    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string|array  $key
     * @return mixed
     */
    public function get($key)
    {
        $cacheData = $this->table()->where('key', $this->getKeyWithPrefix($key))->first();

        return $cacheData ? $this->decodeFromSaved($cacheData['value']) : null;
    }

    /**
     * Store an item in the cache for a given number of minutes.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @param  float|int  $minutes
     * @return bool
     */
    public function put($key, $value, $minutes)
    {
        $expiration = ($this->getTime() + (int) ($minutes * 60)) * 1000;

        return (bool) $this->table()->where('key', $this->getKeyWithPrefix($key))->update(
            ['value' => $this->encodeForSave($value), 'expiration' => new UTCDateTime($expiration)],
            ['upsert' => true]
        );
    }

    /**
     * Increment or decrement an item in the cache.
     *
     * @param  string  $key
     * @param  int  $value
     * @param  Closure  $callback
     * @return int|bool
     */
    protected function incrementOrDecrement($key, $value, Closure $callback)
    {
        if (isset($this->connection->transaction)) {
            return parent::incrementOrDecrement($key, $value, $callback);
        }

        $currentValue = $this->get($key);

        if ($currentValue === null) {
            return false;
        }

        $newValue = $callback($currentValue, $value);

        if ($this->put($key, $newValue)) {
            return $newValue;
        }

        return false;
    }

    /**
     * Format the key to always search for
     *
     * @param string $key
     * @return string
     */
    protected function getKeyWithPrefix(string $key)
    {
        return $this->getPrefix() . $key;
    }

    /**
     * Encode data for save
     *
     * @param mixed $data
     * @return string
     */
    protected function encodeForSave($data)
    {
        return serialize($data);
    }

    /**
     * Decode data from save
     *
     * @param string $data
     * @return mixed
     */
    protected function decodeFromSaved($data)
    {
        return unserialize($data);
    }
}