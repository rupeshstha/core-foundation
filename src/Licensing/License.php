<?php

namespace CoreFoundation\Licensing;

use Illuminate\Support\Str;

/**
 * TODO make a different package and manage license from there.
 */
class License
{
    const INVALID = 0;

    const DEVELOPMENT = 1;

    const UNLICENSED = 2;

    public string $id;

    public string $name;

    public bool $licensed;

    public bool $verified;

    public string $url;

    public string $seller;

    public string $latestVersion;

    public string $domain;

    public function fill(array $properties): self
    {
        foreach ($properties as $property => $value) {
            $setter = 'set'.ucfirst($property);

            if (method_exists($this, $setter)) {
                $this->{$setter}($value);

                continue;
            }

            if (property_exists($this, $property)) {
                $this->{$property} = $value;
            }
        }

        return $this;
    }

    public function setDomain($url)
    {
        $this->domain = Utils::getDomain($url);
    }

    public function isValid()
    {
        return $this->valid;
    }

    public function checkDomain($url): bool
    {
        $incoming = Utils::getDomain($url);

        return Str::contains($incoming, ['localhost', $this->domain])
            || Str::endsWith($incoming, '.test');
    }
}
