<?php

namespace hiapi\endpoints;

use hiapi\endpoints\Exception\EndpointBuildingException;

class EndpointConfig implements \ArrayAccess
{
    private $config = [];

    /**
     * @param string $property
     * @param $value
     * @return $this
     * @throws EndpointBuildingException
     */
    public function set(string $property, $value): self
    {
        if (array_key_exists($property, $this->config)) {
            throw new EndpointBuildingException(sprintf(
                'Property "%s" is already set, use $config->overwrite() if you want to change it.',
                $property
            ));
        }

        $this->config[$property] = $value;

        return $this;
    }

    public function overwrite(string $property, $value): self
    {
        $this->unset($property);

        return $this->set($property, $value);
    }

    public function unset(string $property): self
    {
        unset($this->config[$property]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function offsetExists($offset)
    {
        return isset($this->config);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet($offset)
    {
        return $this->config[$offset];
    }

    /**
     * @inheritDoc
     */
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset($offset)
    {
        $this->unset($offset);
    }
}
