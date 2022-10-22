<?php

namespace TelegramApiParser\Parsers\Telegram;

use TelegramApiParser\Parsers\ResponseParseInterface;

class TelegramResponse implements ResponseParseInterface, \ArrayAccess, \Countable
{
    private array $items = [];

    public function offsetExists(mixed $offset): bool
    {
        return key_exists($offset, $this->items);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!empty($offset)) {
            $this->items[$offset] = $value;
            return;
        }

        $this->items[] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function __toArray(): array
    {
        return $this->toArray();
    }

    public function toArray(): array
    {
        return $this->items;
    }
}