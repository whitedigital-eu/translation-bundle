<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\Service;

final class CollectionCount
{
    private int $count = 0;

    public function getCount(): int
    {
        return $this->count;
    }

    public function setCount(int $count): self
    {
        $this->count = $count;

        return $this;
    }
}
